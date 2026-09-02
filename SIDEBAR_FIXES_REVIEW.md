# Sidebar Fixes Pre-Review

## Audit Date: 2026-06-11

---

## Issue 1 — Controllers returning raw JSON on web routes

**Root cause:** `index()` methods were written for an API but are served on web/session routes.
The browser receives `Content-Type: application/json` and renders a JSON blob instead of HTML.

| Controller | Method | Line | Response |
|---|---|---|---|
| `SupplierController` | `index()` | 37 | `response()->json(['data' => $suppliers])` |
| `GoodsReceivedNoteController` | `index()` | 28 | `response()->json(['data' => $grns])` |
| `RequisitionController` | `index()` | 23 | `response()->json(['data' => $requisitions])` |
| `ExpenseController` | `index()` | 45 | `response()->json(['data' => $query->paginate(20)->withQueryString()])` |

**Fix (Phase 2):** Add `$request->expectsJson()` branch — return JSON for API/AJAX callers,
return `view()` for browser requests. `UserController::index()` already does this correctly.

**Missing views to create (Phase 3):**
- `resources/views/grn/index.blade.php` — directory exists, file missing
- `resources/views/requisitions/index.blade.php` — only `show.blade.php` exists
- `resources/views/expenses/index.blade.php` — only `show.blade.php` + `export-pdf.blade.php` exist
- `resources/views/suppliers/index.blade.php` — **EXISTS**, no new file needed

**View style contract:** All new views must use app CSS classes (`.card`, `.btn`, `.badge`,
`.table-wrapper`, `.empty-state`) as seen in `resources/views/suppliers/index.blade.php`.
No Tailwind classes — the app uses a custom CSS design system with CSS variables (`var(--primary)`, etc.).

---

## Issue 2 — `/users` returns 403 for super_admin

**Root cause:** Route uses `role:Super Admin` (space + capital) but the seeded role name is
`super_admin` (underscore, lowercase). Spatie role middleware does a case-sensitive string match.

```php
// routes/web.php line 425 — WRONG
Route::middleware('role:Super Admin')->resource('users', UserController::class);
```

Seeder creates: `Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])`.

**Fix (Phase 4):** Switch to permission-based middleware to match sidebar and permission matrix:
```php
Route::middleware('permission:users.manage_all')->resource('users', UserController::class);
```

---

## Issue 3 — POS terminal empty cart state

**File:** `resources/views/pos/terminal.blade.php`

The empty-cart `<div id="cart-empty">` uses `h-full py-16` which forces it to fill the
entire cart panel height, making the icon visually oversized on short viewports.

**Fix (Phase 5):** Replace `h-full py-16` with `py-8` so the empty state sits naturally
without stretching to fill the panel.

---

## Files to create / modify

| Phase | Action | File |
|---|---|---|
| 2 | Modify | `app/Http/Controllers/SupplierController.php` |
| 2 | Modify | `app/Http/Controllers/GoodsReceivedNoteController.php` |
| 2 | Modify | `app/Http/Controllers/RequisitionController.php` |
| 2 | Modify | `app/Http/Controllers/ExpenseController.php` |
| 3 | Create | `resources/views/grn/index.blade.php` |
| 3 | Create | `resources/views/requisitions/index.blade.php` |
| 3 | Create | `resources/views/expenses/index.blade.php` |
| 4 | Modify | `routes/web.php` |
| 5 | Modify | `resources/views/pos/terminal.blade.php` |
| 6 | Create | `tests/Feature/SidebarTest.php` |
