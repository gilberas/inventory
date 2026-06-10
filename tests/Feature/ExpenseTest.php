<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ExpenseApprovalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $creator;
    private User   $manager;
    private int    $warehouseId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['expenses.view', 'expenses.create', 'expenses.manage'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->tenant = Tenant::create([
            'name'   => 'Expense Test Co',
            'slug'   => 'expense-test',
            'status' => 'active',
            'config' => ['plan' => 'professional', 'expense_approval_threshold' => 50000],
        ]);

        $this->creator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->creator->givePermissionTo(['expenses.view', 'expenses.create']);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->manager->givePermissionTo(['expenses.view', 'expenses.create', 'expenses.manage']);

        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Test Branch',
            'code'       => 'WH-EXP-01',
            'is_default' => true,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function expensePayload(array $overrides = []): array
    {
        return array_merge([
            'category'     => 'Rent',
            'description'  => 'Monthly office rent',
            'amount'       => 30000,
            'expense_date' => today()->toDateString(),
            'branch_id'    => $this->warehouseId,
        ], $overrides);
    }

    // ── Test 1: Amount above threshold → pending_approval + notification ──────

    public function test_expense_above_threshold_requires_approval(): void
    {
        Notification::fake();
        $this->actingAs($this->creator);

        $response = $this->postJson(route('expenses.store'), $this->expensePayload(['amount' => 100000]));

        $response->assertStatus(201);
        $response->assertJson(['expense' => ['status' => Expense::STATUS_PENDING_APPROVAL]]);

        $this->assertDatabaseHas('expenses', [
            'amount' => '100000.00',
            'status' => Expense::STATUS_PENDING_APPROVAL,
        ]);

        // Manager must receive the approval notification (Hard Rule §3)
        Notification::assertSentTo($this->manager, ExpenseApprovalNotification::class);
    }

    // ── Test 2: Amount at or below threshold → auto-approved ─────────────────

    public function test_expense_below_threshold_auto_approved(): void
    {
        Notification::fake();
        $this->actingAs($this->creator);

        $response = $this->postJson(route('expenses.store'), $this->expensePayload(['amount' => 50000]));

        $response->assertStatus(201);
        $response->assertJson(['expense' => ['status' => Expense::STATUS_APPROVED]]);

        // No approval notification should be sent
        Notification::assertNothingSent();
    }

    // ── Test 3: Approved expense cannot be edited ─────────────────────────────

    public function test_approved_expense_cannot_be_edited(): void
    {
        $this->actingAs($this->creator);

        // Create an approved expense directly
        $expense = Expense::create(array_merge($this->expensePayload(), [
            'created_by' => $this->creator->id,
            'status'     => Expense::STATUS_APPROVED,
        ]));

        $response = $this->putJson(route('expenses.update', $expense), $this->expensePayload([
            'amount'      => 99999,
            'description' => 'Trying to edit an approved expense',
        ]));

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Only draft expenses can be edited.']);

        // Amount should be unchanged
        $this->assertDatabaseHas('expenses', [
            'id'     => $expense->id,
            'status' => Expense::STATUS_APPROVED,
        ]);
    }

    // ── Test 4: Receipt stored with correct path ──────────────────────────────

    public function test_receipt_stored_with_correct_path(): void
    {
        Storage::fake('local');
        $this->actingAs($this->creator);

        $expense = Expense::create(array_merge($this->expensePayload(), [
            'created_by' => $this->creator->id,
            'status'     => Expense::STATUS_DRAFT,
        ]));

        // Minimal valid JPEG (magic bytes FF D8 FF) — avoids GD extension requirement
        $jpegBytes = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";
        $file = UploadedFile::fake()->createWithContent('receipt.jpg', $jpegBytes);

        $response = $this->postJson(route('expenses.receipt', $expense), ['receipt' => $file]);

        $response->assertOk();
        $response->assertJsonStructure(['message', 'receipt_path']);

        $expense->refresh();
        $this->assertNotNull($expense->receipt_path);

        // Path must follow {tenant_id}/expenses/{year}/ pattern
        $expectedPrefix = "{$this->tenant->id}/expenses/" . now()->year;
        $this->assertStringStartsWith($expectedPrefix, $expense->receipt_path);

        // File must exist on disk
        Storage::disk('local')->assertExists($expense->receipt_path);
    }

    // ── Test 5: Monthly summary returns correct category totals ───────────────

    public function test_monthly_summary_correct_totals(): void
    {
        $this->actingAs($this->manager);
        $month = now()->format('Y-m');

        // Create approved expenses this month
        foreach ([10000, 20000] as $amount) {
            Expense::create([
                'created_by'   => $this->creator->id,
                'branch_id'    => $this->warehouseId,
                'category'     => 'Rent',
                'description'  => 'Rent expense',
                'amount'       => $amount,
                'expense_date' => now()->toDateString(),
                'status'       => Expense::STATUS_APPROVED,
            ]);
        }
        Expense::create([
            'created_by'   => $this->creator->id,
            'branch_id'    => $this->warehouseId,
            'category'     => 'Electricity',
            'description'  => 'Electric bill',
            'amount'       => 5000,
            'expense_date' => now()->toDateString(),
            'status'       => Expense::STATUS_APPROVED,
        ]);

        // Set a budget for Rent via HTTP so auth/tenant_id are correctly wired
        $this->postJson(route('expenses.budgets.store'), [
            'category'      => 'Rent',
            'month'         => $month,
            'budget_amount' => 40000,
            'branch_id'     => $this->warehouseId,
        ])->assertStatus(201);

        $response = $this->getJson(route('expenses.summary', [
            'month'     => $month,
            'branch_id' => $this->warehouseId,
        ]));

        $response->assertOk();
        $response->assertJson(['month' => $month]);


        $data = collect($response->json('data'));

        // Rent: 10000 + 20000 = 30000
        $rent = $data->firstWhere('category', 'Rent');
        $this->assertNotNull($rent);
        $this->assertEqualsWithDelta(30000, $rent['total_spent'], 0.01);
        $this->assertEqualsWithDelta(40000, $rent['budget'], 0.01);
        $this->assertEqualsWithDelta(10000, $rent['variance'], 0.01); // 40000 - 30000

        // Grand total: 30000 + 5000 = 35000
        $this->assertEqualsWithDelta(35000, $response->json('grand_total'), 0.01);
    }

    // ── Test 6: Expenses filtered by branch_id ────────────────────────────────

    public function test_expenses_are_branch_scoped(): void
    {
        $this->actingAs($this->creator);

        $branch2 = DB::table('warehouses')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Branch 2',
            'code'       => 'WH-EXP-02',
            'is_default' => false,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // One expense per branch
        Expense::create(array_merge($this->expensePayload(), [
            'created_by' => $this->creator->id,
            'status'     => Expense::STATUS_APPROVED,
            'branch_id'  => $this->warehouseId,
        ]));
        Expense::create(array_merge($this->expensePayload(), [
            'created_by' => $this->creator->id,
            'status'     => Expense::STATUS_APPROVED,
            'branch_id'  => $branch2,
        ]));

        // Filter by branch 1 only
        $response = $this->getJson(route('expenses.index', ['branch_id' => $this->warehouseId]));
        $response->assertOk();

        $items = $response->json('data.data');
        $this->assertCount(1, $items, 'Only branch-1 expense should appear when filtered by branch_id.');
        $this->assertEquals($this->warehouseId, $items[0]['branch_id']);
    }
}
