<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class ProductsExport extends DefaultValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings
{
    public function __construct(private readonly ?int $warehouseId = null) {}

    public function headings(): array
    {
        return ['SKU', 'BARCODE', 'NAMA', 'SATUAN', 'KONVERSI', 'DEPARTEMEN', 'KATEGORI', 'SUB KATEGORI', 'HARGA BELI', 'HARGA JUAL RETAIL', 'LABA(%)', 'RAK', 'STOK'];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (in_array($cell->getColumn(), ['A', 'B'], true) && $value !== null) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        // Keep user-controlled text from becoming Excel formulas.
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function collection()
    {
        return Product::with(['category', 'units', 'warehouses'])->orderBy('title')->get()->flatMap(function ($product) {
            $stock = $this->warehouseId
                ? (int) ($product->warehouses->firstWhere('id', $this->warehouseId)?->pivot->stock ?? 0)
                : $product->stockTotal();
            $units = $product->units;
            if ($product->master_key) {
                $units = $units->filter(fn ($unit) => $unit->pivot->master_data !== null);
            }
            if ($units->isEmpty()) {
                return [[
                    $product->sku, $product->barcode, $product->title, $product->unit_name ?: 'PCS',
                    (float) ($product->conversion ?: 1), $product->department, $product->category?->name,
                    $product->sub_category, $product->buy_price, $product->sell_price,
                    $product->profit_percent, $product->rack, $stock / (float) ($product->conversion ?: 1),
                ]];
            }

            return $units->map(function ($unit) use ($product, $stock) {
                $source = $unit->pivot->master_data ?? [];
                $factor = (float) $unit->pivot->conversion_factor;
                $base = $unit->pivot->is_base;

                return [
                    $source['sku'] ?? ($base ? $product->sku : $product->sku.'-'.$unit->code),
                    $source['barcode'] ?? $unit->pivot->barcode ?? $product->barcode,
                    $base ? $product->title : ($source['nama'] ?? $product->title), $unit->code, $factor,
                    $base ? $product->department : ($source['departemen'] ?? $product->department),
                    $source['kategori'] ?? $product->category?->name,
                    $base ? $product->sub_category : ($source['sub_kategori'] ?? $product->sub_category),
                    $base ? $product->buy_price : (float) $unit->pivot->buy_price,
                    $base ? $product->sell_price : (float) $unit->pivot->sell_price,
                    $base ? $product->profit_percent : ($source['laba'] ?? $product->profit_percent),
                    $base ? $product->rack : ($source['rak'] ?? $product->rack),
                    $stock / max(0.0001, $factor),
                ];
            })->values();
        });
    }
}
