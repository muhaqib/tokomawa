<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\StockMutationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductsImport implements ToCollection, WithHeadingRow
{
    private int $rowCount = 0;

    public function __construct(private readonly ?int $warehouseId = null, private readonly ?int $userId = null) {}

    public function collection(Collection $rows): void
    {
        // Ignore formatted but empty rows, without accepting incomplete nonempty rows.
        $rows = $rows->filter(fn ($row) => collect($row)->contains(fn ($value) => $value !== null && trim((string) $value) !== ''));
        $normalized = $rows->map(function ($row, $index) {
            $row = $row->toArray();
            $row['harga_jual'] = ($row['harga_jual_retail'] ?? $row['harga_jual'] ?? null) ?: 0;
            $row['harga_beli'] = ($row['harga_beli'] ?? null) ?: 0;
            $row['satuan'] = trim($row['satuan'] ?? '') ?: 'PCS';
            $row['konversi'] = $row['konversi'] ?? 1;
            $row['nama'] = trim($row['nama'] ?? '');
            foreach (['sku', 'barcode'] as $field) {
                if (isset($row[$field])) {
                    $row[$field] = (string) $row[$field];
                }
            }
            $row['_line'] = $index + 2;
            Validator::make($row, [
                'nama' => 'required|string|max:255',
                'departemen' => 'nullable|string|max:255',
                'kategori' => 'nullable|string|max:255',
                'sub_kategori' => 'nullable|string|max:255',
                'rak' => 'nullable|string|max:255',
                'laba' => 'nullable|numeric',
                'sku' => 'nullable|string|max:255',
                'barcode' => 'nullable|max:100',
                'satuan' => 'required|string|max:10',
                'konversi' => 'required|numeric|gt:0|max:1000000000',
                'harga_beli' => 'nullable|numeric|min:0',
                'harga_jual' => 'nullable|numeric|min:0',
                'stok' => 'nullable|numeric|min:0',
                'min_stok' => 'nullable|integer|min:0',
                'max_stok' => 'nullable|integer|min:0',
                'tipe_pajak' => 'nullable|in:exclusive,inclusive',
                'tarif_pajak' => 'nullable|numeric|min:0|max:100',
            ])->validate();

            return $row;
        });

        // A blank barcode can still belong to another unit of the same named item.
        $barcodesByName = $normalized->filter(fn ($r) => filled($r['barcode'] ?? null))
            ->mapWithKeys(fn ($r) => [$r['nama'] => (string) $r['barcode']]);
        $groups = $normalized->groupBy(fn ($r) => filled($r['barcode'] ?? null)
            ? 'barcode:'.$r['barcode']
            : (isset($barcodesByName[$r['nama']]) ? 'barcode:'.$barcodesByName[$r['nama']] : 'name:'.$r['nama']));

        $warehouse = $this->warehouseId ? Warehouse::findOrFail($this->warehouseId) : Warehouse::where('is_active', true)->orderBy('sort_order')->first();
        if (! $warehouse) {
            throw ValidationException::withMessages(['file' => 'Tidak ada gudang aktif untuk stok impor.']);
        }

        DB::transaction(function () use ($groups, $warehouse) {
            foreach ($groups as $identity => $group) {
                $base = $group->sortBy('konversi')->first();
                if ($group->pluck('satuan')->duplicates()->isNotEmpty()) {
                    throw ValidationException::withMessages(['file' => "Satuan produk berulang: {$base['nama']}."]);
                }
                $key = hash('sha256', $identity);
                $product = Product::where('master_key', $key)->lockForUpdate()->first();
                if (! $product && filled($base['barcode'] ?? null)) {
                    $product = Product::where('barcode', (string) $base['barcode'])->lockForUpdate()->first();
                }
                if ($product?->is_composite) {
                    throw ValidationException::withMessages(['file' => 'Produk komposit tidak dapat ditimpa melalui impor master.']);
                }
                $category = Category::firstOrCreate(
                    ['name' => trim($base['kategori'] ?? '') ?: 'Umum'],
                    ['description' => '', 'image' => 'default.png']
                );
                $sku = (string) ($base['sku'] ?? '');
                if ($sku === '') {
                    $sku = $product?->sku ?? 'SKU-'.substr($key, 0, 20);
                }
                if (Product::where('sku', $sku)->when($product, fn ($q) => $q->whereKeyNot($product->id))->exists()) {
                    throw ValidationException::withMessages(['file' => "SKU {$sku} sudah digunakan produk lain."]);
                }
                $isNew = ! $product;
                $product ??= new Product;
                $product->fill([
                    'master_key' => $key,
                    'barcode' => $product->barcode ?? (str_starts_with($identity, 'barcode:') ? substr($identity, 8) : 'AUTO-'.substr($key, 0, 20)),
                    'sku' => $sku,
                    'title' => $base['nama'],
                    'category_id' => $category->id,
                    'buy_price' => ($base['harga_beli'] ?? 0) / (float) $base['konversi'],
                    'sell_price' => $base['harga_jual'] / (float) $base['konversi'],
                    'unit_name' => (float) $base['konversi'] === 1.0 ? $base['satuan'] : 'PCS',
                    // Stock is always stored in individual base units.
                    'conversion' => 1,
                    'department' => $base['departemen'] ?? null,
                    'sub_category' => $base['sub_kategori'] ?? null,
                    'profit_percent' => $base['laba'] ?? null,
                    'rack' => $base['rak'] ?? null,
                    'description' => $base['deskripsi'] ?? $product->description,
                    'min_stock' => $base['min_stok'] ?? $product->min_stock ?? 0,
                    'max_stock' => $base['max_stok'] ?? $product->max_stock ?? 0,
                    'tax_type' => $base['tipe_pajak'] ?? $product->tax_type ?? 'exclusive',
                    'tax_rate' => $base['tarif_pajak'] ?? $product->tax_rate ?? 0,
                    'stock' => $product->stock ?? 0,
                ])->save();

                $stockValues = $group->filter(fn ($r) => isset($r['stok']) && $r['stok'] !== '')
                    ->map(fn ($r) => (float) $r['stok'] * (float) $r['konversi']);
                foreach ($stockValues as $value) {
                    if (abs($value - round($value)) > 0.001 || abs($value - $stockValues->first()) > 0.001) {
                        throw ValidationException::withMessages(['file' => "Stok satuan tidak konsisten atau tidak menghasilkan satuan dasar bulat: {$base['nama']}."]);
                    }
                }
                if ($stockValues->isNotEmpty() || $isNew) {
                    $pivot = ProductWarehouse::firstOrCreate(['product_id' => $product->id, 'warehouse_id' => $warehouse->id], ['stock' => 0]);
                    $pivot = ProductWarehouse::whereKey($pivot->id)->lockForUpdate()->firstOrFail();
                    $before = (int) $pivot->stock;
                    $after = (int) round($stockValues->first() ?? 0);
                    $pivot->update(['stock' => $after]);
                    $product->update(['stock' => $product->warehouses()->sum('product_warehouse.stock')]);
                    if ($before !== $after) {
                        app(StockMutationService::class)->recordMutation($product, $warehouse->id, 'product_import', $product->id, $after > $before ? 'in' : 'out', abs($after - $before), $before, $after, 'Impor master Excel', $this->userId);
                    }
                }
                if ((float) $base['konversi'] !== 1.0) {
                    $unit = Unit::firstOrCreate(['code' => 'PCS'], ['name' => 'Pieces', 'symbol' => 'pcs']);
                    $product->units()->syncWithoutDetaching([$unit->id => ['is_base' => true, 'conversion_factor' => 1, 'buy_price' => $product->buy_price, 'sell_price' => $product->sell_price]]);
                }
                foreach ($group as $row) {
                    $unit = Unit::firstOrCreate(['code' => $row['satuan']], ['name' => $row['satuan'], 'symbol' => $row['satuan']]);
                    $product->units()->syncWithoutDetaching([$unit->id => [
                        'is_base' => (float) $row['konversi'] === 1.0,
                        'conversion_factor' => $row['konversi'],
                        'buy_price' => $row['harga_beli'] ?? 0,
                        'sell_price' => $row['harga_jual'],
                        'barcode' => isset($row['barcode']) ? (string) $row['barcode'] : null,
                        'master_data' => $row,
                    ]]);
                    $this->rowCount++;
                }
            }
        });
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
