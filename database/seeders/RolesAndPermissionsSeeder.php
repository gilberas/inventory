<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Platform / SaaS admin
            ['name' => 'platform.manage'],
            ['name' => 'subscriptions.manage'],
            ['name' => 'subscriptions.manage_own'],

            // Users & system
            ['name' => 'users.manage_all'],
            ['name' => 'users.manage_branch'],
            ['name' => 'system.configure'],
            ['name' => 'system.configure_tenant'],

            // Dashboard
            ['name' => 'dashboard.view_all'],
            ['name' => 'dashboard.view_branch'],
            ['name' => 'dashboard.view_financial'],

            // Products & catalogue (routes: products.view, products.manage)
            ['name' => 'products.view'],
            ['name' => 'products.manage'],

            // Inventory (routes: inventory.adjust, inventory.audit, inventory.audit_count, inventory.view)
            ['name' => 'inventory.view'],
            ['name' => 'inventory.adjust'],
            ['name' => 'inventory.audit'],
            ['name' => 'inventory.audit_count'],

            // Branch stock transfers (routes: transfers.view/create/approve/dispatch/receive)
            ['name' => 'transfers.view'],
            ['name' => 'transfers.create'],
            ['name' => 'transfers.approve'],
            ['name' => 'transfers.dispatch'],
            ['name' => 'transfers.receive'],

            // Purchasing (routes: purchases.view/create/manage/receive)
            ['name' => 'purchases.view'],
            ['name' => 'purchases.create'],
            ['name' => 'purchases.manage'],
            ['name' => 'purchases.receive'],

            // Sales / POS (routes: sales.view/create/manage, sidebar: sales.process)
            ['name' => 'sales.process'],   // kept for sidebar backward-compat
            ['name' => 'sales.view'],
            ['name' => 'sales.create'],
            ['name' => 'sales.manage'],

            // Customers
            ['name' => 'customers.manage'],
            ['name' => 'customers.manage_own'],

            // Expenses (routes: expenses.view/create/manage)
            ['name' => 'expenses.view'],
            ['name' => 'expenses.create'],
            ['name' => 'expenses.manage'],

            // Reports (routes: reports.view, reports.financial, reports.vat)
            ['name' => 'reports.view'],
            ['name' => 'reports.financial'],
            ['name' => 'reports.financial_summary'],
            ['name' => 'reports.vat'],

            // Suppliers (sidebar: suppliers.manage/view)
            ['name' => 'suppliers.manage'],
            ['name' => 'suppliers.view'],

            // Employees & attendance (routes: employees.view/create/edit/delete, attendance.manage)
            ['name' => 'employees.manage_all'],    // kept for sidebar backward-compat
            ['name' => 'employees.manage_branch'], // kept for sidebar backward-compat
            ['name' => 'employees.view'],
            ['name' => 'employees.create'],
            ['name' => 'employees.edit'],
            ['name' => 'employees.delete'],
            ['name' => 'attendance.manage'],

            // Audit logs (sidebar: audit_logs.view_all/own)
            ['name' => 'audit_logs.view_all'],
            ['name' => 'audit_logs.view_own'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name'], 'guard_name' => 'web']);
        }

        $totalPermissions = Permission::count();

        // Role 1: Super Admin — all permissions, no tenant
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // Role 2: Business Owner — full tenant control, no platform.manage
        $businessOwner = Role::firstOrCreate(['name' => 'business_owner', 'guard_name' => 'web']);
        $businessOwner->syncPermissions([
            'subscriptions.manage_own',
            'users.manage_all',
            'system.configure_tenant',
            'dashboard.view_all', 'dashboard.view_financial',
            'products.manage', 'products.view',
            'inventory.view', 'inventory.adjust', 'inventory.audit', 'inventory.audit_count',
            'transfers.view', 'transfers.create', 'transfers.approve', 'transfers.dispatch', 'transfers.receive',
            'purchases.view', 'purchases.create', 'purchases.manage', 'purchases.receive',
            'sales.process', 'sales.view', 'sales.create', 'sales.manage',
            'customers.manage', 'customers.manage_own',
            'expenses.view', 'expenses.create', 'expenses.manage',
            'reports.view', 'reports.financial', 'reports.financial_summary', 'reports.vat',
            'suppliers.manage', 'suppliers.view',
            'employees.manage_all', 'employees.view', 'employees.create', 'employees.edit', 'employees.delete',
            'attendance.manage',
            'audit_logs.view_own',
        ]);

        // Role 3: Branch Manager — branch-scoped, no full financials
        $branchManager = Role::firstOrCreate(['name' => 'branch_manager', 'guard_name' => 'web']);
        $branchManager->syncPermissions([
            'users.manage_branch',
            'dashboard.view_branch',
            'products.manage', 'products.view',
            'inventory.view', 'inventory.adjust', 'inventory.audit', 'inventory.audit_count',
            'transfers.view', 'transfers.create', 'transfers.approve', 'transfers.dispatch', 'transfers.receive',
            'purchases.view', 'purchases.create', 'purchases.manage', 'purchases.receive',
            'sales.process', 'sales.view', 'sales.create', 'sales.manage',
            'customers.manage',
            'expenses.view', 'expenses.create', 'expenses.manage',
            'reports.view', 'reports.financial_summary',
            'suppliers.manage', 'suppliers.view',
            'employees.manage_branch', 'employees.view',
            'attendance.manage',
        ]);

        // Role 4: Cashier — front-line POS only
        $cashier = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier->syncPermissions([
            'sales.process', 'sales.view', 'sales.create',
            'customers.manage_own',
            'products.view',
        ]);

        // Role 5: Storekeeper — physical stock management only
        $storekeeper = Role::firstOrCreate(['name' => 'storekeeper', 'guard_name' => 'web']);
        $storekeeper->syncPermissions([
            'products.view',
            'inventory.view', 'inventory.adjust', 'inventory.audit', 'inventory.audit_count',
            'transfers.view', 'transfers.create', 'transfers.receive',
            'purchases.view', 'purchases.receive',
            'suppliers.view',
        ]);

        // Role 6: Accountant — financial visibility only
        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions([
            'dashboard.view_financial',
            'purchases.view',
            'expenses.view', 'expenses.create', 'expenses.manage',
            'reports.view', 'reports.financial', 'reports.financial_summary', 'reports.vat',
            'suppliers.view',
        ]);

        $this->command->info("✅ 6 roles and {$totalPermissions} permissions seeded successfully.");
        $this->command->table(
            ['Role', 'Permissions Count'],
            Role::withCount('permissions')->get()
                ->map(fn ($r) => [$r->name, $r->permissions_count])
                ->toArray()
        );
    }
}
