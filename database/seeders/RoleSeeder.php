<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // ── Reset cached roles/permissions ────────────────────────────────────
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Define all permissions ────────────────────────────────────────────
        $permissions = [
            // Products
            'view products', 'create products', 'edit products', 'delete products',
            // Inventory
            'view inventory', 'adjust inventory', 'transfer stock',
            // Purchases
            'view purchases', 'create purchases', 'approve purchases', 'receive purchases',
            // Sales
            'view sales', 'create sales', 'dispatch sales',
            // Suppliers & Customers
            'view suppliers', 'manage suppliers',
            'view customers', 'manage customers',
            // Warehouses
            'view warehouses', 'manage warehouses',
            // Reports
            'view reports',
            // Users
            'manage users',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // ── Create roles with permissions ─────────────────────────────────────

        // Super Admin — all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // Inventory Manager
        $invManager = Role::firstOrCreate(['name' => 'Inventory Manager', 'guard_name' => 'web']);
        $invManager->syncPermissions([
            'view products', 'create products', 'edit products',
            'view inventory', 'adjust inventory', 'transfer stock',
            'view purchases', 'receive purchases',
            'view suppliers',
            'view warehouses', 'manage warehouses',
            'view reports',
        ]);

        // Storekeeper
        $storekeeper = Role::firstOrCreate(['name' => 'Storekeeper', 'guard_name' => 'web']);
        $storekeeper->syncPermissions([
            'view products',
            'view inventory', 'adjust inventory', 'transfer stock',
            'receive purchases',
            'view warehouses',
        ]);

        // Procurement Officer
        $procurement = Role::firstOrCreate(['name' => 'Procurement Officer', 'guard_name' => 'web']);
        $procurement->syncPermissions([
            'view products',
            'view purchases', 'create purchases', 'approve purchases',
            'view suppliers', 'manage suppliers',
            'view reports',
        ]);

        // Sales Officer
        $sales = Role::firstOrCreate(['name' => 'Sales Officer', 'guard_name' => 'web']);
        $sales->syncPermissions([
            'view products',
            'view sales', 'create sales', 'dispatch sales',
            'view customers', 'manage customers',
            'view reports',
        ]);

        // Auditor (read-only)
        $auditor = Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
        $auditor->syncPermissions([
            'view products', 'view inventory', 'view purchases',
            'view sales', 'view suppliers', 'view customers',
            'view warehouses', 'view reports',
        ]);

        // ── Create default Super Admin user ───────────────────────────────────
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@inventory.com'],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make('password'),
            ]
        );
        $adminUser->assignRole('Super Admin');

        // ── Create a demo storekeeper ─────────────────────────────────────────
        $storeUser = User::firstOrCreate(
            ['email' => 'storekeeper@inventory.com'],
            [
                'name'     => 'Store Keeper',
                'password' => Hash::make('password'),
            ]
        );
        $storeUser->assignRole('Storekeeper');

        $this->command->info('✅ Roles, permissions, and users seeded.');
        $this->command->info('   Admin login: admin@inventory.com / password');
    }
}
