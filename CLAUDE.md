# SmartStock ERP — CLAUDE.md

## Project Overview

**SmartStock ERP** is a multi-tenant SaaS ERP built for Tanzanian and East African
businesses. It is a Laravel 13.8 / PHP 8.3 web application backed by MySQL, using
Spatie Laravel Permission for RBAC. The MVP covers Inventory, Purchasing, Sales, and
Reporting.

> **Current state:** Single-tenant MVP. Multi-tenancy (tenant_id scoping, subscription
> plans, feature flags) is the next major workstream. Every new model and migration must
> be written tenant-ready even before the full tenant infrastructure exists.

---

## Tech Stack

| Layer          | Technology                             |
|----------------|----------------------------------------|
| Framework      | Laravel 13.8                           |
| PHP            | 8.3                                    |
| Database       | MySQL (SQLite for tests)               |
| Auth / RBAC    | `spatie/laravel-permission` ^7.4       |
| Queue          | Laravel Queue (database driver, MVP)   |
| Testing        | PHPUnit 12 (`composer test`)           |
| Asset Pipeline | Vite + npm                             |
| Code style     | Laravel Pint (`vendor/bin/pint`)       |

---

## Directory Layout (key paths)

```
app/
  Http/Controllers/   — one controller per resource
  Models/             — Eloquent models (must all extend TenantModel once created)
  Providers/          — AppServiceProvider only (MVP)
database/
  migrations/         — prefixed by domain: 000010-products, 000030-purchasing, etc.
  factories/          — UserFactory only so far
routes/
  web.php             — all routes (no api.php yet)
tests/
  Feature/            — one file per controller / endpoint group
  Unit/               — model helpers, pure logic
```

---

## Module Map → SRS Sections

| Module           | Controllers / Models                                      | SRS Section |
|------------------|-----------------------------------------------------------|-------------|
| Catalogue        | ProductController, CategoryController, BrandController, UnitController | §3.1 |
| Warehouse        | WarehouseController (+ locations)                         | §3.2 |
| Inventory        | InventoryController, StockBalance, InventoryTransaction   | §3.3 |
| Stock Transfers  | StockTransferController                                   | §3.4 |
| Purchasing       | SupplierController, PurchaseOrderController               | §3.5 |
| Sales            | CustomerController, SalesOrderController                  | §3.6 |
| Audit / Logging  | ActivityLog model, `activity_logs` table                  | §3.7 |
| Users / Auth     | AuthController, UserController, Spatie Roles              | §3.8 |
| Reports          | ReportController                                          | §3.9 |

---

## Roles & Permission Matrix

Roles are managed by `spatie/laravel-permission`. Current roles: **Super Admin**,
**Manager**, **Storekeeper**, **Sales Rep**, **Viewer**.

| Permission                   | Super Admin | Manager | Storekeeper | Sales Rep | Viewer |
|------------------------------|:-----------:|:-------:|:-----------:|:---------:|:------:|
| products.*                   | ✓           | ✓       | ✓           | view      | view   |
| categories/brands/units.*    | ✓           | ✓       | ✓           | —         | view   |
| warehouses.*                 | ✓           | ✓       | view        | —         | view   |
| inventory.adjust             | ✓           | ✓       | ✓           | —         | —      |
| inventory.view               | ✓           | ✓       | ✓           | ✓         | ✓      |
| transfers.*                  | ✓           | ✓       | ✓           | —         | view   |
| purchases.*                  | ✓           | ✓       | receive     | —         | view   |
| sales.*                      | ✓           | ✓       | —           | ✓         | view   |
| reports.*                    | ✓           | ✓       | —           | own       | view   |
| users.*                      | ✓           | —       | —           | —         | —      |

Apply the `role:` middleware (Spatie) or `permission:` middleware per route group.
The `users` resource already uses `middleware('role:Super Admin')`.

---

## Data Model Notes

### Naming conventions
- Tables: `snake_case`, plural (`sales_orders`, `inventory_transactions`)
- Models: `PascalCase`, singular (`SalesOrder`, `InventoryTransaction`)
- Foreign keys: `{table_singular}_id` (`product_id`, `warehouse_id`)

### Key relationships
```
Product → has many ProductBatch, ProductImage, StockBalance, InventoryTransactionItem
Warehouse → has many WarehouseLocation, StockBalance, InventoryTransaction
SalesOrder → belongs to Customer, Warehouse; has many SalesOrderItem, Payment(morph)
PurchaseOrder → belongs to Supplier, Warehouse; has many PurchaseOrderItem, GoodsReceipt
StockTransfer → belongs to from_warehouse, to_warehouse; has many StockTransferItem
InventoryTransaction → has many InventoryTransactionItem (type: IN/OUT/ADJ/TRANSFER)
ActivityLog → belongs to User; polymorphic via model_type / model_id
```

### StockBalance helpers
`StockBalance` uses static helpers (not Eloquent `increment`) to avoid PHP 8+ method
visibility conflicts:
- `StockBalance::addStock($productId, $warehouseId, $qty)`
- `StockBalance::removeStock($productId, $warehouseId, $qty)`
- `StockBalance::reserveStock / releaseStock / adjustStock`

---

## Hard Rules (enforced on every change)

1. **Tenant scoping** — Every new Eloquent model must extend `App\Models\TenantModel`
   (a base model with a global scope on `tenant_id`). Never skip this.

2. **POS / Sales transaction** — Sale creation, payment recording, and inventory
   decrement must be wrapped in a single `DB::transaction()`.

3. **Queue-only notifications** — All notifications (`Mail`, `Notification`) must be
   dispatched via `dispatch()` / `Notification::route()->queue()`. Never call
   `sendNow()` or dispatch synchronously inside a request.

4. **Audit log (append-only)** — Write an `ActivityLog` record for every data mutation
   (create / update / delete on major models). The `activity_logs` table must never be
   updated or deleted — insert only.

5. **Composite index on new major tables** — Every new migration for a top-level domain
   table must include `$table->index(['tenant_id', 'status'])`.

6. **RBAC on every route** — Apply `permission:` or `role:` middleware to every new
   route. Check the Permission Matrix above. Never leave a route unprotected except the
   public home and auth routes.

7. **Feature flag middleware for plan limits** — Subscription plan limits
   (e.g. max products, max warehouses) must be checked in a dedicated middleware class,
   never inside a controller action.

8. **PHPUnit feature test per endpoint** — Write a `tests/Feature/` test class for every
   new controller. Run `composer test` before committing.

9. **MVP scope** — Do not implement features outside the Module Map above unless
   explicitly requested.

10. **Conventional Commits** — All commits: `feat:`, `fix:`, `chore:`, `test:`,
    `docs:`, `refactor:`. Breaking changes get a `!` suffix (`feat!:`).

---

## Workflow for Every Task

1. State the module and SRS section.
2. List files to create or modify.
3. Make the changes.
4. Run `composer test` — all tests must pass.
5. Show a git diff summary and proposed commit message for approval before committing.

---

## Running the Project

```bash
# First-time setup
composer setup          # install deps, .env, migrate, npm build

# Development (runs server + queue + logs + vite concurrently)
composer dev

# Tests only
composer test

# Code style (auto-fix)
./vendor/bin/pint
```

---

## Known Gaps / Backlog (do not implement without explicit ask)

- `TenantModel` base class and `tenant_id` migration columns not yet created
- No `api.php` routes — all routes are web/session-auth
- No subscription / billing module
- `Payments` model file is named plural (`Payments.php`) — should be `Payment.php`;
  fix when touching that model
- `SalesOrderController::deliver()` calls `StockBalance::adjust()` which does not exist;
  the correct method is `StockBalance::adjustStock()` — fix when touching that controller
- No seeders for roles/permissions yet
- No feature tests exist beyond Laravel's default stubs
