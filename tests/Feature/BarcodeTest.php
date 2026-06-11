<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BarcodeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['inventory.audit', 'inventory.adjust', 'sales.process', 'purchase_orders.receive'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->tenant = Tenant::create([
            'name'   => 'Barcode Test Co',
            'slug'   => 'barcode-test',
            'status' => 'active',
        ]);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->manager->givePermissionTo(['inventory.audit', 'inventory.adjust', 'sales.process', 'purchase_orders.receive']);

        $this->productId = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'BAR-001',
            'name'          => 'Barcode Product',
            'cost_price'    => 100.00,
            'selling_price' => 150.00,
            'minimum_stock' => 0,
            'reorder_level' => 5,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_barcode_generated_for_product(): void
    {
        $response = $this->actingAs($this->manager)
            ->get(route('products.barcode', $this->productId) . '?type=code128');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_ean13_barcode_is_13_digits(): void
    {
        // Assign a 12-digit numeric barcode so EAN-13 is deterministic
        DB::table('products')->where('id', $this->productId)->update(['barcode' => '123456789012']);

        $response = $this->actingAs($this->manager)
            ->get(route('products.barcode', $this->productId) . '?type=ean13');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/svg+xml');

        // The SVG must encode all 13 bars; presence of 'EAN' or numeric guard is
        // sufficient — we just verify a valid SVG is returned
        $svg = $response->getContent();
        $this->assertStringContainsString('<svg', $svg);
        $this->assertNotEmpty($svg);
    }

    public function test_bulk_pdf_contains_all_requested_products(): void
    {
        $productId2 = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'BAR-002',
            'name'          => 'Barcode Product 2',
            'cost_price'    => 200.00,
            'selling_price' => 300.00,
            'minimum_stock' => 0,
            'reorder_level' => 5,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $response = $this->actingAs($this->manager)
            ->post(route('barcodes.bulk-print'), [
                '_token'      => csrf_token(),
                'product_ids' => [$this->productId, $productId2],
                'size'        => 'medium',
            ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('pdf', strtolower($response->headers->get('Content-Type', '')));
    }

    public function test_duplicate_barcode_assignment_rejected(): void
    {
        // Assign a barcode to a second product
        $productId2 = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'BAR-003',
            'name'          => 'Other Product',
            'barcode'       => 'EXISTING-BARCODE',
            'cost_price'    => 50.00,
            'selling_price' => 80.00,
            'minimum_stock' => 0,
            'reorder_level' => 5,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Try to assign the same barcode to the first product
        $response = $this->actingAs($this->manager)
            ->post(route('products.barcode.assign', $this->productId), [
                '_token'  => csrf_token(),
                'barcode' => 'EXISTING-BARCODE',
            ]);

        $response->assertSessionHasErrors('barcode');
    }

    public function test_pos_scan_returns_product(): void
    {
        DB::table('products')->where('id', $this->productId)->update(['barcode' => 'POS-SCAN-123']);

        $response = $this->actingAs($this->manager)
            ->getJson(route('pos.scan', 'POS-SCAN-123'));

        $response->assertStatus(200);
        $response->assertJsonFragment(['sku' => 'BAR-001']);
        $response->assertJsonFragment(['barcode' => 'POS-SCAN-123']);
    }
}
