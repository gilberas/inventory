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

            // Products & catalogue
            ['name' => 'products.view'],
            ['name' => 'products.manage'],

            // Inventory
            ['name' => 'inventory.adjust'],
            ['name' => 'inventory.audit'],
            ['name' => 'inventory.audit_count'],
            ['name' => 'inventory.transfer'],
            ['name' => 'inventory.transfer_dispatch'],

            // Purchasing
            ['name' => 'purchase_orders.manage'],
            ['name' => 'purchase_orders.receive'],

            // Suppliers
            ['name' => 'suppliers.manage'],
            ['name' => 'suppliers.view'],

            // Sales / POS
            ['name' => 'sales.process'],

            // Customers
            ['name' => 'customers.manage'],
            ['name' => 'customers.manage_own'],

            // Expenses
            ['name' => 'expenses.view'],
            ['name' => 'expenses.manage'],

            // Reports
            ['name' => 'reports.financial'],
            ['name' => 'reports.financial_summary'],
            ['name' => 'reports.vat'],

            // Employees & attendance
            ['name' => 'employees.manage_all'],
            ['name' => 'employees.manage_branch'],

            // Audit logs
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
            'inventory.adjust', 'inventory.audit', 'inventory.audit_count',
            'inventory.transfer', 'inventory.transfer_dispatch',
            'purchase_orders.manage', 'purchase_orders.receive',
            'suppliers.manage', 'suppliers.view',
            'sales.process',
            'customers.manage', 'customers.manage_own',
            'expenses.view', 'expenses.manage',
            'reports.financial', 'reports.financial_summary', 'reports.vat',
            'employees.manage_all', 'employees.manage_branch',
            'audit_logs.view_own',
        ]);

        // Role 3: Branch Manager — branch-scoped, no full financials
        $branchManager = Role::firstOrCreate(['name' => 'branch_manager', 'guard_name' => 'web']);
        $branchManager->syncPermissions([
            'users.manage_branch',
            'dashboard.view_branch',
            'products.manage', 'products.view',
            'inventory.adjust', 'inventory.audit', 'inventory.audit_count',
            'inventory.transfer', 'inventory.transfer_dispatch',
            'purchase_orders.manage', 'purchase_orders.receive',
            'suppliers.manage', 'suppliers.view',
            'sales.process',
            'customers.manage',
            'expenses.view', 'expenses.manage',
            'reports.financial_summary',
            'employees.manage_branch',
            'audit_logs.view_own',
        ]);

        // Role 4: Cashier — front-line POS only
        $cashier = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier->syncPermissions([
            'sales.process',
            'customers.manage_own',
            'products.view',
        ]);

        // Role 5: Storekeeper — physical stock management only
        $storekeeper = Role::firstOrCreate(['name' => 'storekeeper', 'guard_name' => 'web']);
        $storekeeper->syncPermissions([
            'products.view',
            'inventory.adjust', 'inventory.audit', 'inventory.audit_count',
            'inventory.transfer',
            'purchase_orders.receive',
            'suppliers.view',
        ]);

        // Role 6: Accountant — financial visibility only
        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions([
            'dashboard.view_financial',
            'purchase_orders.manage',
            'expenses.view', 'expenses.manage',
            'reports.financial', 'reports.financial_summary', 'reports.vat',
            'suppliers.view',
            'audit_logs.view_own',
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
