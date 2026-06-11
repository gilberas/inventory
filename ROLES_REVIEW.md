# Roles & Dashboard Review

## Spatie Package: INSTALLED
`spatie/laravel-permission` is in `composer.json`.
Migration: `database/migrations/2026_05_19_054937_create_permission_tables.php` — EXISTS.
`User` model uses `HasRoles` trait — YES.
Guard: `web` (single guard in `config/auth.php`).

## Existing Roles in DB: NONE
DB is empty (`Role::all()` returns `[]`). The `RoleSeeder` exists but has not been run.

## Existing Permissions in DB: NONE
DB is empty (`Permission::all()` returns `[]`). Seeder not run.

## Seeder Files Found
| File | Contents |
|------|----------|
| `database/seeders/DatabaseSeeder.php` | Only creates a single `test@example.com` user — no roles/permissions |
| `database/seeders/RoleSeeder.php` | Seeds 6 roles (Super Admin, Inventory Manager, Storekeeper, Procurement Officer, Sales Officer, Auditor) with 23 legacy permissions — **NOT yet run**, and role names/permissions do NOT match SRS §5.1/§15 |

## Dashboard Controller: EXISTS
`app/Http/Controllers/DashboardController.php` — exists and is functional.
- Checks `canSelectBranch` via `hasRole(['super_admin','business_owner'])`.
- Loads one set of metrics for **all roles** from `DashboardMetricsService`.
- Returns a single view `dashboard.index` regardless of role.
- Does **NOT** return different views or different data sets per role.

## Dashboard Views
| File | Notes |
|------|-------|
| `resources/views/dashboard.blade.php` | Exists (likely legacy/unused) |
| `resources/views/dashboard/index.blade.php` | Active view — single unified layout for all roles |

No role-specific dashboard views exist (no `super_admin.blade.php`, `business_owner.blade.php`, etc.).

## Role Middleware: PARTIAL
Existing middleware (no role-checking middleware):
- `Require2FA.php`
- `SessionTimeout.php`
- `CheckProductLimit.php`
- `CheckWarehouseLimit.php`
- `CheckPOSTerminalLimit.php`
- `CheckStockTransferFeature.php`

Spatie's `role:` and `permission:` middleware are available via the package but no
dedicated middleware class wraps role-based dashboard routing.

## What needs to be built

1. **Replace `RoleSeeder` with `RolesAndPermissionsSeeder`** — new seeder with the 6 SRS roles
   (`super_admin`, `business_owner`, `branch_manager`, `cashier`, `storekeeper`, `accountant`)
   and 33 SRS §15 permissions. The existing `RoleSeeder` has wrong role names and only 23
   permissions using a different naming convention.

2. **Create `DemoUsersSeeder`** — one demo user per role, assigned to a demo tenant/branch/warehouse.

3. **Expand `DashboardController`** — add a `match($role)` dispatch so each role gets its own
   private method returning role-specific data from the correct scoped queries.

4. **Create 6 role-specific Blade views** under `resources/views/dashboard/`:
   `super_admin.blade.php`, `business_owner.blade.php`, `branch_manager.blade.php`,
   `cashier.blade.php`, `storekeeper.blade.php`, `accountant.blade.php`.

5. **Update sidebar/navigation** — apply `@can`/`@role` directives so menu items that a role
   cannot access are not rendered at all (not just disabled).

6. **Create `tests/Feature/RolesPermissionsTest.php`** — 20 tests covering seeder correctness,
   per-role dashboard data, access control, and navigation rendering.
