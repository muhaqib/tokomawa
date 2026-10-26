<?php

namespace Tests\Feature\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        Warehouse::create(['code' => 'PUSAT', 'name' => 'Pusat', 'type' => 'main', 'is_active' => true, 'sort_order' => 0]);
    }

    public function test_original_workbook_can_be_imported_repeatedly(): void
    {
        $options = ['file' => 'master_toko_coba.xlsx', '--warehouse' => 'PUSAT', '--force' => true];
        $this->artisan('products:import', $options)->assertSuccessful();
        $count = Product::count();
        $this->assertGreaterThan(0, $count);
        $this->assertEquals(235.23, Product::where('title', 'FUM BURGER')->firstOrFail()->buy_price);
        $stocks = Product::orderBy('id')->pluck('stock', 'id')->all();
        $this->artisan('products:import', $options)->assertSuccessful();
        $this->assertSame($count, Product::count());
        $this->assertSame($stocks, Product::orderBy('id')->pluck('stock', 'id')->all());
        foreach (Product::all() as $product) {
            $this->assertSame($product->stock, $product->stockTotal());
        }
    }

    public function test_dry_run_leaves_no_imported_data(): void
    {
        $unitsBefore = Unit::count();
        $this->artisan('products:import', ['file' => base_path('master_toko_coba.xlsx'), '--warehouse' => 'PUSAT', '--dry-run' => true])
            ->expectsOutputToContain('Simulasi berhasil')->assertSuccessful();
        $this->assertSame(0, Product::count());
        $this->assertSame(0, Category::count());
        $this->assertSame($unitsBefore, Unit::count());
        $this->assertDatabaseCount('product_warehouse', 0);
        $this->assertDatabaseCount('stock_mutations', 0);
    }

    public function test_requires_explicit_active_warehouse_and_readable_file(): void
    {
        $this->artisan('products:import', ['file' => 'missing.xlsx', '--warehouse' => 'PUSAT'])->assertFailed();
        $this->artisan('products:import', ['file' => 'master_toko_coba.xlsx'])->assertFailed();
        Warehouse::query()->update(['is_active' => false]);
        $this->artisan('products:import', ['file' => 'master_toko_coba.xlsx', '--warehouse' => 'PUSAT'])->assertFailed();
        $this->assertSame(0, Product::count());
    }

    public function test_declining_confirmation_does_not_import(): void
    {
        $this->artisan('products:import', ['file' => 'master_toko_coba.xlsx', '--warehouse' => 'PUSAT'])
            ->expectsConfirmation('Lanjutkan impor?', 'no')->assertFailed();
        $this->assertSame(0, Product::count());
    }
}
