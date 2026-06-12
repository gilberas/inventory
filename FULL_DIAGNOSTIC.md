# SmartStock ERP — Full System Diagnostic
**Date:** 2026-06-12

---

## 1. Route Audit

### 264 routes defined total. Key findings:

#### MISSING routes (referenced in Blade views but not defined):
| View-referenced name | Actual issue | Files affected |
|---|---|---|
| `customers.create` | Not in web.php; CustomerController has no `create()` | `customers/index.blade.php` and others |
| `customers.edit` | Not in web.php; CustomerController has no `edit()` | `customers/show.blade.php` and others |
| `admin.tenants.index` | No admin/* routes exist at all | `partials/sidebar.blade.php` |
| `admin.plans.index` | No admin/* routes exist at all | `partials/sidebar.blade.php` |
| `pos.index` | Route is named `pos.terminal`, not `pos.index` | `dashboard/cashier.blade.php`, `partials/sidebar.blade.php` |
| `purchase-orders.create` | Route is `purchases.create`, not `purchase-orders.create` | `requisitions/show.blade.php` |
| `sales.addPayment` | Route is `sales.payment`, not `sales.addPayment` | `sales/show.blade.php` |
| `settings.index` | No settings route exists | `partials/sidebar.blade.php` |

#### Existing customer routes (correctly defined):
`customers.index`, `customers.store`, `customers.show`, `customers.update`,
`customers.destroy`, `customers.segments`, `customers.balance`, `customers.history`,
`customers.tags.assign`, `customers.tags.remove`

#### CustomerController — missing methods:
- `create()` — missing
- `edit()` — missing

---

## 2. User Permissions

| Email | Role | Perm count |
|---|---|---|
| superadmin@smartstock.test | super_admin | 33 |
| owner@demo.test | business_owner | 24 |
| manager@demo.test | branch_manager | 17 |
| cashier@demo.test | cashier | 3 |
| store@demo.test | storekeeper | 6 |
| accounts@demo.test | accountant | 7 |
| test@example.com | (none) | 0 |
| gillyimo2009@gmail.com | (none) | 0 |
| gillyimo2002@gmail.com | (none) | 0 |

All permission slugs in DB: `audit_logs.view_all`, `audit_logs.view_own`, `customers.manage`,
`customers.manage_own`, `dashboard.view_all`, `dashboard.view_branch`, `dashboard.view_financial`,
`employees.manage_all`, `employees.manage_branch`, `expenses.manage`, `expenses.view`,
`inventory.adjust`, `inventory.audit`, `inventory.audit_count`, `inventory.transfer`,
`inventory.transfer_dispatch`, `platform.manage`, `products.manage`, `products.view`,
`purchase_orders.manage`, `purchase_orders.receive`, `reports.financial`, `reports.financial_summary`,
`reports.vat`, `sales.process`, `subscriptions.manage`, `subscriptions.manage_own`,
`suppliers.manage`, `suppliers.view`, `system.configure`, `system.configure_tenant`,
`users.manage_all`, `users.manage_branch`

---

## 3. Expenses (403 investigation)

- Route `/expenses` uses `permission:expenses.view`
- `expenses.view` exists in DB ✓
- `business_owner` has `expenses.manage` + `expenses.view` ✓
- `branch_manager` has `expenses.manage` ✓ (but NOT `expenses.view` — mismatch on index route)
- `accountant` has `expenses.manage` + `expenses.view` ✓
- **Issue:** `branch_manager` lacks `expenses.view` permission but the index route requires it.
  The route uses `permission:expenses.view` but `branch_manager` only has `expenses.manage`.

---

## 4. VAT Report (500 error)

Controller: `FinancialController@vatReport` → `FinancialController@computeVat`

`$collectedByRate` and `$paidByRate` are `Illuminate\Support\Collection` objects
(from DB query builder `->get()`). Stored in `Cache::remember($key, 600, ...)`.

Blade line 87: `@if($data['collectedByRate']->isNotEmpty() || $data['paidByRate']->isNotEmpty())`

The 500 error "class Illuminate\Support\Collection not loaded before unserialize()" occurs
when cached serialized data is deserialized. Root cause: Cache stores the compact() array
with Collection objects. On cache hit, deserialization could fail if autoloader hasn't yet
loaded the Collection class at that point. Fix: wrap in `collect()` to guarantee type, and
add null-safe check in blade.

---

## 5. Dashboard

DashboardController already routes to separate views per role:
- `super_admin` → `dashboard.super_admin` ✓
- `business_owner` → `dashboard.business_owner` ✓
- `branch_manager` → `dashboard.branch_manager` ✓
- `cashier` → `dashboard.cashier` ✓
- `storekeeper` → `dashboard.storekeeper` ✓
- `accountant` → `dashboard.accountant` ✓

superAdminDashboard() uses raw `DB::table('tenants')` (bypasses tenant scope) ✓

---

## 6. AppServiceProvider

Gate::before bypass already present:
`Gate::before(fn ($user, $ability) => $user->hasRole('super_admin') ? true : null)` ✓

---

## 7. Fixes Required (priority order)

1. **web.php** — add `customers.create` and `customers.edit` routes
2. **CustomerController** — add `create()` and `edit()` methods
3. **resources/views/customers/create.blade.php** — create view
4. **resources/views/customers/edit.blade.php** — create view
5. **Fix blade references**: `pos.index` → `pos.terminal`, `purchase-orders.create` → `purchases.create`, `sales.addPayment` → `sales.payment`
6. **Add stub routes**: `settings.index`, `admin.tenants.index`, `admin.plans.index`
7. **Fix branch_manager expenses.view**: Add `expenses.view` permission to branch_manager role (or change route middleware)
8. **Fix VAT Report**: wrap query results in `collect()`, add null-safe blade check
