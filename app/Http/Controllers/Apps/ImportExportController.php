<?php

namespace App\Http\Controllers\Apps;

use App\Exports\CustomersExport;
use App\Exports\ProductsExport;
use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Imports\CustomersImport;
use App\Imports\ProductsImport;
use App\Models\Outlet;
use App\Models\Warehouse;
use App\Services\OutletAccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;

class ImportExportController extends Controller
{
    public function __construct(private readonly OutletAccessService $outletAccessService) {}

    public function exportProducts(Request $request)
    {
        return Excel::download(new ProductsExport($this->productWarehouse($request)->id), 'produk.xlsx');
    }

    public function exportCustomers()
    {
        return Excel::download(new CustomersExport, 'customer.xlsx');
    }

    public function exportTransactions(Request $request)
    {
        $warehouseIds = $request->user()->isSuperAdmin()
            ? null
            : $this->outletAccessService->warehousesFor($request->user())->pluck('id')->all();
        $includeLegacy = Outlet::active()->count() <= 1;

        return Excel::download(new TransactionsExport($request, $warehouseIds, $includeLegacy), 'transaksi.xlsx');
    }

    public function importProducts(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $import = new ProductsImport($this->productWarehouse($request)->id, $request->user()->id);
        Excel::import($import, $request->file('file'));

        $successCount = $import->getRowCount();

        return back()->with('success', "Import selesai. {$successCount} produk diimport.");
    }

    public function importCustomers(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $import = new CustomersImport;
        Excel::import($import, $request->file('file'));

        return back()->with('success', 'Import customer selesai.');
    }

    private function productWarehouse(Request $request): Warehouse
    {
        $request->validate(['warehouse_id' => 'nullable|integer|exists:warehouses,id']);
        $warehouse = $this->outletAccessService->warehousesFor($request->user())
            ->first(fn ($candidate) => (! $request->filled('warehouse_id') || $candidate->id === (int) $request->warehouse_id)
                && $this->outletAccessService->canUseWarehouse($request->user(), $candidate));
        if (! $warehouse) {
            throw ValidationException::withMessages(['file' => 'Pilih gudang aktif yang dapat diakses untuk ekspor/impor.']);
        }

        return $warehouse;
    }

    public function downloadTemplate(string $type)
    {
        $headings = match ($type) {
            'products' => (new ProductsExport)->headings(),
            'customers' => ['nama', 'telepon', 'alamat'],
            default => abort(404),
        };

        return Excel::download(
            new class($headings) implements FromArray, WithHeadings
            {
                public function __construct(private array $headings) {}

                public function headings(): array
                {
                    return $this->headings;
                }

                public function array(): array
                {
                    return [];
                }
            },
            "template-{$type}.xlsx"
        );
    }
}
