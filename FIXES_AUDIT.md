# SmartStock ERP — Fixes Audit (Phase 1)
**Date:** 2026-06-11  
**Status:** Read-only audit, no changes made.

---

## 1. CustomerController.index() — Returns JSON, Never Serves HTML View

**Severity:** BLOCKER  
**File:** `app/Http/Controllers/CustomerController.php:17-39`

`index()` always returns `response()->json(['data' => $customers])` with no
`$request->expectsJson()` guard.  Opening `/customers` in any browser returns
a raw JSON blob.

`resources/views/customers/index.blade.php` **exists** and is ready to receive
the `$customers` paginator — it is simply never called.

```php
// Current (broken)
public function index(Request $request)
{
    // ... query building ...
    return response()->json(['data' => $customers]);   // <-- always JSON
}
```

**Related gap:** `customers/index.blade.php:5` calls `route('customers.create')`
but that route **does not exist** in `web.php`.  `customers/form.blade.php`
(a full-page create/edit form) exists but is never reachable.

`CustomerController` also has **no `create()` method**.

---

## 2. Sidebar — Transfers Link Always Hidden (Stale Permission Names)

**Severity:** BLOCKER  
**File:** `resources/views/partials/sidebar.blade.php:89`

```blade
{{-- BROKEN — 'transfers.view' and 'transfers.create' are NOT seeded --}}
@canany(['transfers.view', 'transfers.create'])
<a href="{{ route('transfers.index') }}">Transfers</a>
@endcanany
```

The Transfers nav item is **invisible for every user including super_admin and
business_owner** because neither `transfers.view` nor `transfers.create` exists
in the permission table.  The seeded names are `inventory.transfer` and
`inventory.transfer_dispatch`.

**Secondary (low priority):** Line 208 includes the stale `employees.view` in an
OR canany — it is harmless because the two correct names follow it, but should
be cleaned up.

---

## 3. Production DB Not Re-Seeded — Role Permission Counts Are Off

**Severity:** HIGH  
**Command to verify:** `php artisan tinker` output from this audit run.

| Role           | DB Count (actual) | Seeder Count (expected) | Delta |
|----------------|:-----------------:|:-----------------------:|:-----:|
| super_admin    | (not checked)     | 33                      | —     |
| business_owner | 24                | 27                      | -3    |
| branch_manager | 17                | 20                      | -3    |
| cashier        | 3                 | 3                        | ✓    |
| storekeeper    | 6                 | 7                        | -1   |
| accountant     | 7                 | 9                        | -2   |

The seeder was rewritten in the previous fix session but `db:seed` was not run
on the production database.  Three newer permissions per owner/manager role are
missing from the assigned sets.

**Fix command:**
```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan permission:cache-reset
php artisan cache:clear
php artisan view:clear
```

---

## 4. One Demo User Has No Role (Gilbert Isidory)

**Severity:** MEDIUM  
**Tinker output:** `Gilbert Isidory → NO ROLE | permissions: 0`

This user exists in the tenanted DB but has no role assigned.  He will see an
empty dashboard and no sidebar navigation.  Needs a role assigned, or the
account should be removed if it is a test artefact.

---

## 5. Controllers That Return JSON on Web Routes — Status Summary

| Controller              | index() returns                      | Status   |
|-------------------------|--------------------------------------|----------|
| `CustomerController`    | `response()->json()` — always        | ❌ BROKEN |
| `ExpenseController`     | `view()` branch behind expectsJson   | ✅ OK    |
| `SupplierController`    | `view()` branch behind expectsJson   | ✅ OK    |
| `GoodsReceivedNoteController` | `view()` branch behind expectsJson | ✅ OK |
| `RequisitionController` | `view()` branch behind expectsJson   | ✅ OK    |
| `UserController`        | `view('users.index')` directly       | ✅ OK    |
| `SalesOrderController`  | `view('sales.index')` directly       | ✅ OK    |
| `SaleController`        | `response()->json()` — always        | ✅ OK (POS API) |

`SaleController` (route `pos.sales.index`) is a JSON API consumed by the POS
JavaScript frontend, not a web page.  The "Sales History" sidebar link points to
`sales.index → SalesOrderController@index` which returns a view.  No fix needed.

---

## 6. Missing expenses.create Route/View — Not a Bug

**Severity:** INFO  
No `expenses.create` GET route and no `expenses/create.blade.php`.  The
`expenses/index.blade.php` New Expense button (`onclick="…newExpenseModal…"`)
opens an inline modal form — creation is intentionally modal-based.  No fix
needed here.

---

## 7. Sidebar Structure — Overall Assessment

The sidebar in `resources/views/partials/sidebar.blade.php` already uses
`@can`, `@canany`, and `@role` directives throughout.  The structure is
role-aware and sound.  Only two items need fixing:

1. **Transfers section (line 89):** stale `transfers.view/transfers.create` → fix to `inventory.transfer` (BLOCKER)
2. **HR section (line 208):** stale `employees.view` inside canany — low priority cleanup

No full sidebar rewrite is needed.

---

## Planned Fixes (Phases 2–7)

| Phase | What                                                         | Files changed |
|-------|--------------------------------------------------------------|---------------|
| 2     | `CustomerController.index()` → add view branch              | `CustomerController.php` |
| 2     | Add `customers.create` route + `create()` method            | `CustomerController.php`, `web.php` |
| 3     | Sidebar Transfers: fix stale permission names               | `sidebar.blade.php` |
| 4     | Re-seed production DB, reset permission cache               | (artisan command only) |
| 5     | Assign role to Gilbert Isidory (or delete the account)      | (tinker/seeder) |
