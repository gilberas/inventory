<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        // ── Demo tenant ───────────────────────────────────────────────────────
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            [
                'name'   => 'Demo Business Ltd',
                'status' => 'active',
                'config' => [
                    'plan'               => 'professional',
                    'currency'           => 'TZS',
                    'timezone'           => 'Africa/Dar_es_Salaam',
                    'loyalty_earn_rate'  => 1,
                    'loyalty_redeem_rate' => 1,
                ],
            ]
        );

        // ── Demo warehouse (serves as the "main branch" for MVP) ──────────────
        $warehouseId = DB::table('warehouses')
            ->where('tenant_id', $tenant->id)
            ->where('is_default', true)
            ->value('id');

        if (! $warehouseId) {
            $warehouseId = DB::table('warehouses')->insertGetId([
                'tenant_id'  => $tenant->id,
                'name'       => 'Main Warehouse',
                'code'       => 'WH-MAIN',
                'is_default' => true,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── One demo user per role ────────────────────────────────────────────
        // super_admin has no tenant — it is a platform-level account
        $users = [
            [
                'name'      => 'Admin User',
                'email'     => 'superadmin@smartstock.test',
                'role'      => 'super_admin',
                'tenant_id' => null,
                'branch_id' => null,
            ],
            [
                'name'      => 'John Owner',
                'email'     => 'owner@demo.test',
                'role'      => 'business_owner',
                'tenant_id' => $tenant->id,
                'branch_id' => null,   // owner sees all branches
            ],
            [
                'name'      => 'Mary Manager',
                'email'     => 'manager@demo.test',
                'role'      => 'branch_manager',
                'tenant_id' => $tenant->id,
                'branch_id' => $warehouseId,
            ],
            [
                'name'      => 'Peter Cashier',
                'email'     => 'cashier@demo.test',
                'role'      => 'cashier',
                'tenant_id' => $tenant->id,
                'branch_id' => $warehouseId,
            ],
            [
                'name'      => 'Amina Storekeeper',
                'email'     => 'store@demo.test',
                'role'      => 'storekeeper',
                'tenant_id' => $tenant->id,
                'branch_id' => $warehouseId,
            ],
            [
                'name'      => 'Grace Accountant',
                'email'     => 'accounts@demo.test',
                'role'      => 'accountant',
                'tenant_id' => $tenant->id,
                'branch_id' => $warehouseId,
            ],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name'              => $data['name'],
                    'password'          => Hash::make('password'),
                    'tenant_id'         => $data['tenant_id'],
                    'branch_id'         => $data['branch_id'],
                    'status'            => 'active',
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$data['role']]);
        }

        $this->command->info('✅ Demo users seeded:');
        $this->command->table(
            ['Name', 'Email', 'Role', 'Password'],
            collect($users)->map(fn ($u) => [$u['name'], $u['email'], $u['role'], 'password'])->toArray()
        );
    }
}
