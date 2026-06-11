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
            ['name' => 'platform.manage',            'module' => 'platform'],
            ['name' => 'subscriptions.manage',        'module' => 'subscriptions'],
            ['name' => 'subscriptions.manage_own',    'module' => 'subscriptions'],
            ['name' => 'users.manage_all',            'module' => 'users'],
            ['name' => 'users.manage_branch',         'module' => 'users'],
            ['name' => 'dashboard.view_all',          'module' => 'dashboard'],
            ['name' => 'dashboard.view_branch',       'module' => 'dashboard'],
            ['name' => 'dashboard.view_financial',    'module' => 'dashboard'],
            ['name' => 'products.manage',             'module' => 'products'],
            ['name' => 'products.view',               'module' => 'products'],
            ['name' => 'sales.process',               'module' => 'sales'],
            ['name' => 'purchase_orders.manage',      'module' => 'purchases'],
            ['name' => 'purchase_orders.receive',     'module' => 'purchases'],
            ['name' => 'inventory.adjust',            'module' => 'inventory'],
            ['name' => 'inventory.transfer',          'module' => 'inventory'],
            ['name' => 'inventory.transfer_dispatch', 'module' => 'inventory'],
            ['name' => 'inventory.audit',             'module' => 'inventory'],
            ['name' => 'inventory.audit_count',       'module' => 'inventory'],
            ['name' => 'expenses.manage',             'module' => 'expenses'],
            ['name' => 'expenses.view',               'module' => 'expenses'],
            ['name' => 'reports.financial',           'module' => 'reports'],
            ['name' => 'reports.financial_summary',   'module' => 'reports'],
            ['name' => 'reports.vat',                 'module' => 'reports'],
            ['name' => 'employees.manage_all',        'module' => 'employees'],
            ['name' => 'employees.manage_branch',     'module' => 'employees'],
            ['name' => 'suppliers.manage',            'module' => 'suppliers'],
            ['name' => 'suppliers.view',              'module' => 'suppliers'],
            ['name' => 'customers.manage',            'module' => 'customers'],
            ['name' => 'customers.manage_own',        'module' => 'customers'],
            ['name' => 'audit_logs.view_all',         'module' => 'audit'],
            ['name' => 'audit_logs.view_own',         'module' => 'audit'],
            ['name' => 'system.configure',            'module' => 'system'],
            ['name' => 'system.configure_tenant',     'module' => 'system'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name'], 'guard_name' => 'web']);
        }

        // Role 1: Super Admin — all permissions, no tenant
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // Role 2: Business Owner — full tenant control, no platform.manage
        $businessOwner = Role::firstOrCreate(['name' => 'business_owner', 'guard_name' => 'web']);
        $businessOwner->syncPermissions([
            'subscriptions.manage_own',
            'users.manage_all',
            'dashboard.view_all',
            'dashboard.view_financial',
            'products.manage',
            'products.view',
            'sales.process',
            'purchase_orders.manage',
            'purchase_orders.receive',
            'inventory.adjust',
            'inventory.transfer',
            'inventory.transfer_dispatch',
            'inventory.audit',
            'expenses.manage',
            'reports.financial',
            'reports.financial_summary',
            'reports.vat',
            'employees.manage_all',
            'suppliers.manage',
            'suppliers.view',
            'customers.manage',
            'customers.manage_own',
            'audit_logs.view_own',
            'system.configure_tenant',
        ]);

        // Role 3: Branch Manager — branch-scoped, no full financials
        $branchManager = Role::firstOrCreate(['name' => 'branch_manager', 'guard_name' => 'web']);
        $branchManager->syncPermissions([
            'users.manage_branch',
            'dashboard.view_branch',
            'products.manage',
            'products.view',
            'sales.process',
            'purchase_orders.manage',
            'purchase_orders.receive',
            'inventory.adjust',
            'inventory.transfer',
            'inventory.transfer_dispatch',
            'inventory.audit',
            'expenses.manage',
            'reports.financial_summary',
            'employees.manage_branch',
            'suppliers.manage',
            'suppliers.view',
            'customers.manage',
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
            'purchase_orders.receive',
            'inventory.adjust',
            'inventory.transfer_dispatch',
            'inventory.audit_count',
            'products.view',
            'suppliers.view',
        ]);

        // Role 6: Accountant — financial visibility only
        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions([
            'dashboard.view_financial',
            'expenses.view',
            'expenses.manage',
            'reports.financial',
            'reports.financial_summary',
            'reports.vat',
            'suppliers.view',
        ]);

        $this->command->info('✅ 6 roles and 33 permissions seeded successfully.');
        $this->command->table(
            ['Role', 'Permissions Count'],
            Role::withCount('permissions')->get()
                ->map(fn ($r) => [$r->name, $r->permissions_count])
                ->toArray()
        );
    }
}
