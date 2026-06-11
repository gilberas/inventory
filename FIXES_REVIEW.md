# SmartStock ERP — Pre-Fix Review

Date: 2026-06-11
Phase: Pre-fix audit for 403 errors, forgot-password, and user profile

---

## 1. Authentication & RBAC State

| Check | Result |
|-------|--------|
| Spatie laravel-permission installed | YES |
| Roles in DB | 6 roles (super_admin, business_owner, branch_manager, cashier, storekeeper, accountant) |
| Permissions in DB | 33 permissions (dot-notation style) |
| `superadmin@smartstock.test` exists | YES — tenant_id=null, super_admin role |
| `can('products.manage')` for super_admin | YES |

---

## 2. Root Cause: 403 Errors

### 2a. Old-style route permission names (CRITICAL)

Products, categories, brands, and warehouses routes use OLD-style names absent from the DB:

| Route Group | Permission Checked | Seeder Has | Status |
|-------------|-------------------|------------|--------|
| `GET /products` | `view products` | `products.view` | MISMATCH |
| `POST /products` | `create products` | `products.manage` | MISMATCH |
| `PUT /products/{id}` | `edit products` | `products.manage` | MISMATCH |
| `DELETE /products/{id}` | `delete products` | `products.manage` | MISMATCH |
| `GET /categories` | `view categories` | _(none)_ | MISSING |
| `POST /categories` | `create categories` | _(none)_ | MISSING |
| `PUT /categories/{id}` | `edit categories` | _(none)_ | MISSING |
| `DELETE /categories/{id}` | `delete categories` | _(none)_ | MISSING |
| `GET /warehouses` | `view warehouses` | _(none)_ | MISSING |
| `POST /warehouses` | `create warehouses` | _(none)_ | MISSING |
| `PUT /warehouses/{id}` | `edit warehouses` | _(none)_ | MISSING |
| `DELETE /warehouses/{id}` | `delete warehouses` | _(none)_ | MISSING |
| `Route::resource('units', ...)` | _(no middleware at all)_ | — | UNPROTECTED |

### 2b. New-style route permissions missing from seeder (CRITICAL)

| Permission in Routes | Routes Affected | Seeder Equivalent | Status |
|---------------------|----------------|-------------------|--------|
| `inventory.view` | `/inventory/out-of-stock`, `/inventory/movements/*` | _(none)_ | MISSING |
| `transfers.view` | `GET /transfers`, `GET /transfers/{id}` | `inventory.transfer` (different name) | MISMATCH |
| `transfers.create` | `POST /transfers` | _(none)_ | MISSING |
| `transfers.approve` | `POST /transfers/{id}/approve` | _(none)_ | MISSING |
| `transfers.dispatch` | `POST /transfers/{id}/dispatch` | `inventory.transfer_dispatch` (different name) | MISMATCH |
| `transfers.receive` | `POST /transfers/{id}/receive` | _(none)_ | MISSING |
| `purchases.view` | `GET /purchases`, `GET /suppliers`, `GET /grn` | `purchase_orders.manage` (different name) | MISMATCH |
| `purchases.create` | `POST /purchases`, `GET /purchases/create` | _(none)_ | MISSING |
| `purchases.manage` | `DELETE /purchases/{id}`, `POST /{id}/approve` | `purchase_orders.manage` (different name) | MISMATCH |
| `purchases.receive` | `GET/POST /{purchase}/receive`, `POST /grn` | `purchase_orders.receive` (different name) | MISMATCH |
| `sales.view` | `GET /customers`, `GET /pos/sales` | _(none)_ | MISSING |
| `sales.create` | `GET /pos` (terminal), `POST /pos/sales` | `sales.process` (different name) | MISMATCH |
| `sales.manage` | `POST /pos/sales/{id}/void` | _(none)_ | MISSING |
| `expenses.create` | `POST /expenses`, `PUT /expenses/{id}` | _(expenses.manage exists but not expenses.create)_ | MISSING |
| `reports.view` | `GET /reports`, `/reports/stock`, `/reports/movements` | _(none)_ | MISSING |
| `employees.view` | `GET /employees`, `GET /attendance/today` | `employees.manage_all/branch` (different name) | MISMATCH |
| `employees.create` | `POST /employees` | _(none)_ | MISSING |
| `employees.edit` | `PUT /employees/{id}` | _(none)_ | MISSING |
| `employees.delete` | `DELETE /employees/{id}` | _(none)_ | MISSING |
| `attendance.manage` | `POST /attendance/{id}/clock-in/out` | _(none)_ | MISSING |

**Impact:** ALL roles except super_admin (once Gate::before is added) get 403 on nearly every module. Cashiers cannot open POS because the terminal route checks `sales.create` but cashier only has `sales.process`.

### 2c. Gate::before bypass: MISSING

`AppServiceProvider` has no `Gate::before` callback. Even though super_admin has all 33 seeded permissions, any route guarded by an old-style or missing permission name 403s for super_admin too.

---

## 3. Forgot Password State

| Check | Result |
|-------|--------|
| `password_reset_tokens` table | EXISTS |
| `must_change_password` column on users | MISSING |
| `ForgotPasswordController` | MISSING |
| `forgot-password.blade.php` | MISSING |
| `forgot-password-sent.blade.php` | MISSING |
| "Forgot Password?" link on login page | MISSING |

---

## 4. User Profile State

| Check | Result |
|-------|--------|
| `profile_photo_path` column on users | MISSING |
| `phone` column on users | MISSING |
| `ProfileController` | MISSING |
| `resources/views/profile/edit.blade.php` | MISSING |
| Profile link in sidebar | MISSING |

---

## 5. Planned Fixes

| Phase | Fix | Approach |
|-------|-----|----------|
| 2a | super_admin bypass all gates | `Gate::before` in AppServiceProvider |
| 2b | Old-style route names | Update products/categories/brands/units/warehouses routes to use `products.view`, `products.manage`, `inventory.audit`, `inventory.adjust` |
| 2c | 20 missing permissions | Extend RolesAndPermissionsSeeder to 53 permissions; assign to roles; update sidebar `@canany` |
| 3 | Forgot password | ForgotPasswordController, 2 views, `must_change_password` migration, login link, banner |
| 4 | User profile | ProfileController, profile/edit view, 3-column migration |
| 5 | Tests | AuthFixesTest.php (15 tests); update RolesPermissionsTest counts |
