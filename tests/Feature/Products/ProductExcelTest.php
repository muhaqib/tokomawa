<?php

namespace Tests\Feature\Products;

use App\Exports\ProductsExport;
use App\Imports\ProductsImport;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ProductExcelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $this->warehouse = Warehouse::create(['code' => 'PUSAT', 'name' => 'Pusat', 'type' => 'main', 'is_active' => true, 'sort_order' => 0]);
    }

    private function rows(): Collection
    {
        return collect([
            collect(['sku' => '0011803', 'barcode' => '8999999612207', 'nama' => 'Produk Karton', 'satuan' => 'KARTON', 'konversi' => 40, 'departemen' => 'FOOD', 'kategori' => '', 'sub_kategori' => 'SHAMPO', 'harga_beli' => 600000, 'harga_jual_retail' => 624000, 'laba' => 4, 'rak' => 'A1', 'stok' => 0.45]),
            collect(['sku' => '8999999612207', 'barcode' => '8999999612207', 'nama' => 'Produk Eceran', 'satuan' => 'RTG', 'konversi' => 1, 'harga_beli' => 15000.23, 'harga_jual_retail' => 17250, 'stok' => 18]),
            collect(['nama' => null, 'barcode' => null]),
        ]);
    }

    public function test_import_converts_units_without_double_counting_stock_and_is_repeatable(): void
    {
        $import = new ProductsImport($this->warehouse->id);
        $import->collection($this->rows());
        $import->collection($this->rows());
        $this->assertSame(1, Product::count());
        $product = Product::firstOrFail();
        $this->assertSame(18, $product->stock);
        $this->assertSame(18, $product->stockTotal());
        $this->assertEquals(15000.23, $product->buy_price);
        $this->assertCount(2, $product->units);
        $export = (new ProductsExport($this->warehouse->id))->collection();
        $carton = $export->first(fn ($row) => $row[3] === 'KARTON');
        $this->assertSame('0011803', $carton[0]);
        $this->assertSame('Produk Karton', $carton[2]);
        $this->assertEquals(0.45, $carton[12]);
        $this->assertSame(4, $import->getRowCount());
    }

    public function test_exported_xlsx_can_be_imported_again(): void
    {
        (new ProductsImport($this->warehouse->id))->collection($this->rows());
        $path = tempnam(sys_get_temp_dir(), 'master-');
        try {
            file_put_contents($path, Excel::raw(new ProductsExport($this->warehouse->id), \Maatwebsite\Excel\Excel::XLSX));
            Excel::import(new ProductsImport($this->warehouse->id), $path, null, \Maatwebsite\Excel\Excel::XLSX);
            $this->assertSame(1, Product::count());
            $this->assertSame(18, Product::firstOrFail()->stockTotal());
            $carton = Product::firstOrFail()->units->firstWhere('code', 'KARTON');
            $this->assertSame('0011803', $carton->pivot->master_data['sku']);
        } finally {
            @unlink($path);
        }
    }

    public function test_import_with_no_base_row_creates_pcs_and_converts_carton_stock(): void
    {
        (new ProductsImport($this->warehouse->id))->collection(collect([$this->rows()[0]]));
        $product = Product::firstOrFail();
        $this->assertSame(18, $product->stock);
        $this->assertEquals(15000, $product->buy_price);
        $this->assertSame('PCS', $product->baseUnit()->code);
        $this->assertCount(1, (new ProductsExport($this->warehouse->id))->collection());
    }

    public function test_blank_codes_link_named_units_and_survive_export_reimport(): void
    {
        $rows = $this->rows();
        $rows[0]['nama'] = $rows[1]['nama'];
        $rows[1]['barcode'] = null;
        $rows[1]['sku'] = null;
        (new ProductsImport($this->warehouse->id))->collection($rows);
        $export = new ProductsExport($this->warehouse->id);
        $headings = ['sku', 'barcode', 'nama', 'satuan', 'konversi', 'departemen', 'kategori', 'sub_kategori', 'harga_beli', 'harga_jual_retail', 'laba', 'rak', 'stok'];
        $roundtrip = $export->collection()->map(fn ($row) => collect(array_combine($headings, $row)));
        (new ProductsImport($this->warehouse->id))->collection($roundtrip);
        $this->assertSame(1, Product::count());
        $this->assertSame(18, Product::firstOrFail()->stock);
    }

    public function test_form_converts_fractional_carton_stock_to_base_units(): void
    {
        $admin = User::where('email', 'arya@gmail.com')->firstOrFail();
        $admin->markEmailAsVerified();
        $this->actingAs($admin)->post(route('products.store'), [
            'title' => 'Produk Karton', 'buy_price' => 600000, 'sell_price' => 624000,
            'stock' => 0.45, 'unit_name' => 'KARTON', 'conversion' => 40,
        ])->assertSessionHasNoErrors()->assertRedirect(route('products.index'));
        $product = Product::firstOrFail();
        $this->assertSame(18, $product->stockTotal());
        $this->assertEquals(15000, $product->buy_price);
        $this->assertSame('PCS', $product->baseUnit()->code);
    }

    public function test_import_updates_only_selected_warehouse(): void
    {
        (new ProductsImport($this->warehouse->id))->collection($this->rows());
        $product = Product::firstOrFail();
        $other = Warehouse::create(['code' => 'OTHER', 'name' => 'Other', 'type' => 'branch', 'is_active' => true]);
        $product->warehouses()->attach($other->id, ['stock' => 7]);
        $product->update(['stock' => 25]);
        (new ProductsImport($this->warehouse->id))->collection($this->rows());
        $this->assertSame(25, $product->fresh()->stock);
        $this->assertSame(25, $product->stockTotal());
        $row = (new ProductsExport($this->warehouse->id))->collection()->first(fn ($row) => $row[3] === 'RTG');
        $this->assertEquals(18, $row[12]);
    }

    public function test_inconsistent_stock_rolls_back_all_changes(): void
    {
        $rows = $this->rows();
        $rows[0]['stok'] = 2;
        try {
            (new ProductsImport($this->warehouse->id))->collection($rows);
            $this->fail('Inconsistent stock should be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }
        $this->assertSame(0, Product::count());
    }

    public function test_form_accepts_optional_identifiers_category_image_and_description(): void
    {
        $admin = User::where('email', 'arya@gmail.com')->firstOrFail();
        $admin->markEmailAsVerified();
        $this->actingAs($admin)->post(route('products.store'), [
            'title' => 'Produk tanpa kode', 'buy_price' => 235.23, 'sell_price' => 350, 'stock' => 0,
            'department' => 'FOOD', 'unit_name' => 'PCS', 'conversion' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('products.index'));
        $product = Product::firstOrFail();
        $this->assertNotEmpty($product->sku);
        $this->assertNotEmpty($product->barcode);
        $this->assertNull($product->image);
        $this->assertSame('Umum', $product->category->name);
    }
}
