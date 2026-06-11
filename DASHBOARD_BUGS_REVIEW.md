# Dashboard Bugs — Pre-Fix Review
_Generated: 2026-06-11_

---

## Phase 1 — Audit Findings

### 1. DashboardController — purchase_requisitions query (500 error)

**File:** `app/Http/Controllers/DashboardController.php:142`

```php
// BUG A — wrong column
->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))

// BUG B — invalid status value
->where('status', 'submitted')
```

**Root cause A:** `purchase_requisitions` table has `branch_id`, NOT `warehouse_id`.  
Columns: `id, tenant_id, branch_id, requested_by, status, notes, created_at, updated_at, deleted_at`

**Root cause B:** Valid statuses per `PurchaseRequisition` model constants:  
`draft`, `pending`, `approved`, `rejected`, `revision_requested`  
`'submitted'` is never stored — the `submit()` controller action transitions to `pending`.

**Fix:** Change to `->where('branch_id', ...)` and `->whereIn('status', ['pending', 'draft'])`.

---

### 2. Table / Column Existence Audit

All tables queried by DashboardController and DashboardMetricsService exist:

| Table | Status | Notes |
|-------|--------|-------|
| purchase_requisitions | EXISTS | Has branch_id NOT warehouse_id |
| sales | EXISTS | Has cashier_id, warehouse_id, branch_id ✓ |
| inventory | EXISTS | Has product_id, warehouse_id, quantity ✓ |
| expenses | EXISTS | Has status, branch_id, amount ✓ |
| purchase_orders | EXISTS | Has warehouse_id, status ✓ |
| stock_transfers | EXISTS | Has from_warehouse_id, to_warehouse_id ✓ |
| branch_transfers | EXISTS | Has from_branch_id, to_branch_id ✓ |
| inventory_audits | EXISTS | ✓ |
| notifications | EXISTS | ✓ |
| pos_sessions | EXISTS | Has cashier_id, tenant_id, status ✓ |
| goods_received_notes | EXISTS | Has warehouse_id ✓ |
| sale_items | EXISTS | ✓ |
| grn_items | EXISTS | ✓ |
| supplier_invoices | EXISTS | ✓ |
| product_batches | EXISTS | ✓ |
| stock_balances | EXISTS | Has warehouse_id, quantity_available ✓ |
| branches | EXISTS | ✓ |
| warehouses | EXISTS | Has branch_id, tenant_id ✓ |
| tenants | EXISTS | ✓ |
| jobs / failed_jobs | EXISTS | ✓ |
| products | EXISTS | Has both minimum_stock AND reorder_level |
| users | EXISTS | Has branch_id, tenant_id, status ✓ |

**Minor inconsistency:** `DashboardMetricsService::inventoryMetrics()` uses `products.minimum_stock`
while `DashboardController::lowStockProducts()` uses `products.reorder_level`. Both columns
exist, so no crash — but the low-stock threshold differs between the overview card and the
detailed list. No migration needed, but the two methods should be aligned.

---

### 3. Variable / Column Grep: DashboardController

`$warehouseId` is set correctly:
- Line 40: `$warehouseId = $request->integer('branch_id') ?: null;` (owner, branch selector)
- Line 47: `$warehouseId = $user->branch_id ? (int) $user->branch_id : null;` (all other roles)

All other `->when($warehouseId, ...)` calls in the controller reference columns that exist:
- `inventory.warehouse_id` ✓
- `stock_balances.warehouse_id` ✓
- `goods_received_notes.warehouse_id` ✓
- `branch_transfers.from_branch_id` / `to_branch_id` ✓

Only BUG A (purchase_requisitions.warehouse_id) causes the crash.

---

### 4. RBAC / Permissions Audit

**Gate::before bypass** — already present in `AppServiceProvider::boot()` ✓

**Role permission counts (actual DB):**

| Role | DB Count | Expected |
|------|----------|----------|
| super_admin | 33 | 33 ✓ |
| business_owner | 24 | 23+ ✓ |
| branch_manager | 17 | 16+ ✓ |
| cashier | 3 | 3 ✓ |
| storekeeper | 6 | 6 ✓ |
| accountant | 7 | 7 ✓ |

**Critical — Routes use permissions that DO NOT exist in the database.**
These cause a 403 (Spatie treats unknown permission as denial) for every non-super_admin user:

| Route Permission Used | Seeded Equivalent / Fix |
|-----------------------|-------------------------|
| `inventory.view` | → `inventory.audit` |
| `transfers.view` | → `inventory.audit` |
| `transfers.create` | → `inventory.transfer` |
| `transfers.approve` | → `inventory.adjust` |
| `transfers.dispatch` | → `inventory.transfer_dispatch` |
| `transfers.receive` | → `inventory.transfer` |
| `purchases.view` | → `purchase_orders.manage\|purchase_orders.receive` |
| `purchases.create` | → `purchase_orders.manage` |
| `purchases.manage` | → `purchase_orders.manage` |
| `purchases.receive` | → `purchase_orders.receive` |
| `sales.view` | → `sales.process` |
| `sales.create` | → `sales.process` |
| `sales.manage` | → `sales.process` |
| `expenses.create` | → `expenses.manage` |
| `reports.view` | → `reports.financial_summary` |
| `employees.view` | → `employees.manage_branch` |
| `employees.create` | → `employees.manage_branch` |
| `employees.edit` | → `employees.manage_branch` |
| `employees.delete` | → `employees.manage_all` |
| `attendance.manage` | → `employees.manage_branch` |

**Additional finding:** `Route::resource('sales', SalesOrderController::class)` and its
companion action routes (confirm/dispatch/deliver/payment/cancel) have NO permission
middleware at all — any authenticated user can access B2B sales orders.

---

### 5. Demo Users

| Email | Role | Permissions | tenant_id |
|-------|------|-------------|-----------|
| owner@demo.test | business_owner | 24 | 2 |
| manager@demo.test | branch_manager | 17 | 2 |
| cashier@demo.test | cashier | 3 | 2 |
| store@demo.test | storekeeper | 6 | 2 |
| accounts@demo.test | accountant | 7 | 2 |
| gillyimo2002@gmail.com | **NO ROLE** | 0 | 1 |

`gillyimo2002@gmail.com` has no role — this user will be logged out immediately by the
`$tenantId === 0` check in the dashboard. (tenant_id=1 is set, so they won't be logged
out — they'll fall through to `match($role)` with `null` role → default view.
The default view is `dashboard.index` which should be safe.)

---

### 6. Routes with middleware — full list

See `routes/web.php`. Every route that has a middleware string referencing a non-existent
permission is listed in Section 4 above. The `auth` middleware and `require.2fa` are both
fine. Spatie `permission:` and `role:` strings are the only problem area.

---

## Summary of Fixes Required

| # | File | Fix |
|---|------|-----|
| 1 | DashboardController.php:141-142 | Change `warehouse_id` → `branch_id`, status `'submitted'` → `['pending','draft']` |
| 2 | routes/web.php | Replace 20 non-existent permission strings with seeded equivalents |
| 3 | routes/web.php:311-316 | Add permission middleware to B2B sales resource routes |
