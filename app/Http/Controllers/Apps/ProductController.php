<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\AuditLogService;
use App\Services\OutletAccessService;
use App\Services\StockMutationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(
        private readonly StockMutationService $stockMutationService,
        private readonly AuditLogService $auditLogService,
        private readonly OutletAccessService $outletAccessService
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $products = Product::when($request->search, function ($products, $search) {
            $products = $products->where('title', 'like', '%'.$search.'%');
        })->with('category')->latest()->paginate($this->perPage())->withQueryString();

        $warehouses = $this->outletAccessService->warehousesFor($request->user());

        return Inertia::render('Dashboard/Products/Index', [
            'products' => $products,
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        // get categories
        $categories = Category::all();

        // get non-composite products for composite component selection
        $products = Product::where('is_composite', false)
            ->orderBy('title')
            ->get(['id', 'title', 'sell_price', 'is_composite']);

        // return inertia
        return Inertia::render('Dashboard/Products/Create', [
            'categories' => $categories,
            'warehouses' => $this->outletAccessService->warehousesFor($request->user())
                ->filter(fn ($warehouse) => $this->outletAccessService->canUseWarehouse($request->user(), $warehouse))->values(),
            'products' => $products,
            'units' => Unit::orderBy('code')->get(['id', 'code', 'symbol']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        /**
         * validate
         */
        $request->validate([
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'barcode' => 'nullable|string|max:255|unique:products,barcode',
            'sku' => 'nullable|string|max:255|unique:products,sku',
            'title' => 'required',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'buy_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'stock' => 'nullable|numeric|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'min_stock' => 'nullable|integer|min:0',
            'max_stock' => 'nullable|integer|min:0',
            'tax_type' => 'nullable|in:exclusive,inclusive',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            ...$this->masterRules(),
            ...$this->compositeRules(),
            ...$this->unitRules(),
        ]);
        $this->prepareMasterInput($request);
        $warehouse = null;
        if ($request->boolean('is_composite')) {
            $this->validateComponentProducts($request);
        } else {
            $warehouse = $request->filled('warehouse_id')
                ? Warehouse::findOrFail($request->warehouse_id)
                : $this->outletAccessService->warehousesFor($request->user())
                    ->first(fn ($candidate) => $this->outletAccessService->canUseWarehouse($request->user(), $candidate));

            if (! $warehouse) {
                throw ValidationException::withMessages(['warehouse_id' => 'Tidak ada gudang aktif yang dapat diakses.']);
            }
            abort_unless($this->outletAccessService->canUseWarehouse($request->user(), $warehouse), 403);
        }

        DB::transaction(function () use ($request, $warehouse) {
            // upload image
            $image = $request->file('image');
            $image?->storeAs('public/products', $image->hashName());

            // create product
            $product = Product::create([
                'image' => $image?->hashName(),
                ...$request->only(array_keys($this->masterRules())),
                'barcode' => $request->barcode,
                'sku' => $request->sku,
                'title' => $request->title,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'buy_price' => $request->buy_price,
                'sell_price' => $request->sell_price,
                'stock' => $request->stock,
                'min_stock' => $request->min_stock ?? 0,
                'max_stock' => $request->max_stock ?? 0,
                'tax_type' => $request->input('tax_type', 'exclusive'),
                'tax_rate' => $request->input('tax_rate', 11.00),
                'is_composite' => $request->boolean('is_composite'),
            ]);

            if ($request->boolean('is_composite')) {
                $this->syncComponents($product, $request->input('components'));
            } else {
                $this->syncUnits($product, $request->input('units'));
                $this->syncMasterUnit($product, $request);

                // Keep global and warehouse stock in the same transaction.
                if ($warehouse) {
                    ProductWarehouse::updateOrCreate(
                        ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                        ['stock' => (int) $request->stock]
                    );
                }

                $this->stockMutationService->recordInitialStock($product, $request->user()?->id, $warehouse?->id);
            }
            $this->auditLogService->log(
                event: 'product.created',
                module: 'products',
                auditable: $product,
                description: 'Produk baru dibuat.',
                after: $this->productAuditPayload($product->fresh())
            );
        });

        // redirect
        return to_route('products.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Product $product)
    {
        // get categories
        $categories = Category::all();

        // get non-composite products for composite component selection
        $products = Product::where('is_composite', false)
            ->where('id', '!=', $product->id)
            ->orderBy('title')
            ->get(['id', 'title', 'sell_price', 'is_composite']);

        return Inertia::render('Dashboard/Products/Edit', [
            'product' => $product->load('components', 'units'),
            'categories' => $categories,
            'products' => $products,
            'units' => Unit::orderBy('code')->get(['id', 'code', 'symbol']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, Product $product)
    {
        $before = $this->productAuditPayload($product);

        /**
         * validate
         */
        $request->validate([
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'barcode' => 'nullable|string|max:255|unique:products,barcode,'.$product->id,
            'sku' => 'nullable|string|max:255|unique:products,sku,'.$product->id,
            'title' => 'required',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'buy_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'max_stock' => 'nullable|integer|min:0',
            'tax_type' => 'nullable|in:exclusive,inclusive',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            ...$this->masterRules(),
            ...$this->compositeRules(),
            ...$this->unitRules(),
        ]);

        $this->prepareMasterInput($request, $product);

        if ($request->boolean('is_composite')) {
            $this->validateComponentProducts($request, $product);
        } elseif ($product->is_composite && $product->components()->exists()) {
            return back()->withErrors([
                'components' => 'Produk komposit harus memiliki minimal satu komponen.',
            ]);
        }

        // check image update
        if ($request->file('image')) {

            // remove old image
            if ($product->getRawOriginal('image')) {
                Storage::disk('local')->delete('public/products/'.basename($product->getRawOriginal('image')));
            }

            // upload new image
            $image = $request->file('image');
            $image->storeAs('public/products', $image->hashName());

            // update product with new image
            $product->update([
                'image' => $image->hashName(),
                ...$request->only(array_keys($this->masterRules())),
                'barcode' => $request->barcode,
                'sku' => $request->sku,
                'title' => $request->title,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'buy_price' => $request->buy_price,
                'sell_price' => $request->sell_price,
                'min_stock' => $request->min_stock ?? 0,
                'max_stock' => $request->max_stock ?? 0,
                'tax_type' => $request->input('tax_type', 'exclusive'),
                'tax_rate' => $request->input('tax_rate', 11.00),
                'is_composite' => $request->boolean('is_composite'),
            ]);

            if ($request->boolean('is_composite')) {
                $this->syncComponents($product, $request->input('components'));
            } else {
                $this->syncUnits($product, $request->input('units'));
            }

            $this->logProductUpdate($product, $before);

            return to_route('products.index');
        }

        // update product without image
        $product->update([
            ...$request->only(array_keys($this->masterRules())),
            'barcode' => $request->barcode,
            'sku' => $request->sku,
            'title' => $request->title,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'buy_price' => $request->buy_price,
            'sell_price' => $request->sell_price,
            'min_stock' => $request->min_stock ?? 0,
            'max_stock' => $request->max_stock ?? 0,
            'tax_type' => $request->input('tax_type', 'exclusive'),
            'tax_rate' => $request->input('tax_rate', 11.00),
            'is_composite' => $request->boolean('is_composite'),
        ]);

        if ($request->boolean('is_composite')) {
            $this->syncComponents($product, $request->input('components'));
        } else {
            $this->syncUnits($product, $request->input('units'));
        }

        $this->logProductUpdate($product, $before);

        // redirect
        return to_route('products.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        // find by ID
        $product = Product::findOrFail($id);
        $before = $this->productAuditPayload($product);

        // remove image
        if ($product->getRawOriginal('image')) {
            Storage::disk('local')->delete('public/products/'.basename($product->getRawOriginal('image')));
        }

        // delete
        $product->delete();

        $this->auditLogService->log(
            event: 'product.deleted',
            module: 'products',
            auditable: $product,
            description: 'Produk dihapus.',
            before: $before
        );

        // redirect
        return back();
    }

    private function masterRules(): array
    {
        return [
            'unit_name' => 'nullable|string|max:10',
            'conversion' => 'nullable|numeric|gt:0',
            'department' => 'nullable|string|max:255',
            'sub_category' => 'nullable|string|max:255',
            'profit_percent' => 'nullable|numeric',
            'rack' => 'nullable|string|max:255',
        ];
    }

    private function prepareMasterInput(Request $request, ?Product $product = null): void
    {
        if (! $product && ! $request->boolean('is_composite') && ! $request->input('units')) {
            $factor = (float) ($request->input('conversion') ?: 1);
            $stock = (float) $request->input('stock', 0) * $factor;
            if (abs($stock - round($stock)) > 0.001) {
                throw ValidationException::withMessages(['stock' => 'Stok × konversi harus menghasilkan satuan dasar bulat.']);
            }
            $request->attributes->set('master_row', [
                'satuan' => $request->input('unit_name') ?: 'PCS',
                'konversi' => $factor,
                'harga_beli' => $request->buy_price,
                'harga_jual' => $request->sell_price,
            ]);
            $request->merge([
                'stock' => (int) round($stock),
                'buy_price' => (float) $request->buy_price / $factor,
                'sell_price' => (float) $request->sell_price / $factor,
                'conversion' => 1,
                'unit_name' => $factor === 1.0 ? ($request->input('unit_name') ?: 'PCS') : 'PCS',
            ]);
        }
        if (! $product && ! $request->attributes->has('master_row')) {
            $stock = (float) ($request->stock ?? 0);
            if ($stock !== floor($stock)) {
                throw ValidationException::withMessages(['stock' => 'Stok satuan dasar harus bulat.']);
            }
            $request->merge(['stock' => (int) $stock]);
        }
        $request->merge([
            'barcode' => $request->input('barcode') ?: ($product?->barcode ?? 'AUTO-'.Str::uuid()),
            'sku' => $request->input('sku') ?: ($product?->sku ?? 'SKU-'.Str::uuid()),
            'category_id' => $request->input('category_id') ?: Category::firstOrCreate(
                ['name' => 'Umum'], ['description' => '', 'image' => 'default.png']
            )->id,
            'unit_name' => $request->input('unit_name') ?: ($product?->unit_name ?? 'PCS'),
            'conversion' => $request->input('conversion') ?: ($product?->conversion ?? 1),
        ]);
    }

    private function syncMasterUnit(Product $product, Request $request): void
    {
        $row = $request->attributes->get('master_row');
        if (! $row || $request->input('units')) {
            return;
        }
        $unit = Unit::firstOrCreate(['code' => $row['satuan']], ['name' => $row['satuan'], 'symbol' => $row['satuan']]);
        if ((float) $row['konversi'] === 1.0) {
            $product->units()->updateExistingPivot($product->units()->pluck('units.id')->all(), ['is_base' => false]);
        }
        $product->units()->syncWithoutDetaching([$unit->id => [
            'is_base' => (float) $row['konversi'] === 1.0,
            'conversion_factor' => $row['konversi'],
            'buy_price' => $row['harga_beli'],
            'sell_price' => $row['harga_jual'],
            'barcode' => $product->barcode,
            'master_data' => $row,
        ]]);
        if ((float) $row['konversi'] !== 1.0 && ! $product->units()->wherePivot('is_base', true)->exists()) {
            $pcs = Unit::firstOrCreate(['code' => 'PCS'], ['name' => 'Pieces', 'symbol' => 'pcs']);
            $product->units()->syncWithoutDetaching([$pcs->id => ['is_base' => true, 'conversion_factor' => 1, 'buy_price' => $product->buy_price, 'sell_price' => $product->sell_price]]);
        }
    }

    private function compositeRules(): array
    {
        return [
            'is_composite' => 'nullable|boolean',
            'components' => 'required_if:is_composite,1,true|array|min:1',
            'components.*.component_product_id' => 'required|integer|distinct|exists:products,id',
            'components.*.qty' => 'required|integer|min:1',
        ];
    }

    private function validateComponentProducts(Request $request, ?Product $product = null): void
    {
        $componentIds = collect($request->input('components'))
            ->pluck('component_product_id')
            ->unique();

        if ($product && $componentIds->contains($product->id)) {
            abort(422, 'Produk tidak bisa menjadi komponen dirinya sendiri.');
        }

        $compositeCount = Product::whereIn('id', $componentIds)->where('is_composite', true)->count();

        if ($compositeCount > 0) {
            abort(422, 'Komponen tidak boleh produk komposit lain.');
        }
    }

    private function syncComponents(Product $product, ?array $components): void
    {
        $product->components()->sync(
            collect($components)->mapWithKeys(fn (array $c) => [
                $c['component_product_id'] => ['qty' => (int) $c['qty']],
            ])
        );
    }

    private function unitRules(): array
    {
        return [
            'units' => 'nullable|array',
            'units.*.unit_id' => 'required|integer|distinct|exists:units,id',
            'units.*.is_base' => 'required|boolean',
            'units.*.conversion_factor' => 'required|numeric|min:0.0001',
            'units.*.buy_price' => 'nullable|numeric|min:0',
            'units.*.sell_price' => 'nullable|numeric|min:0',
            'units.*.barcode' => 'nullable|string|max:255',
        ];
    }

    private function syncUnits(Product $product, ?array $units): void
    {
        $rows = collect($units ?? [])
            ->keyBy('unit_id')
            ->map(fn (array $u) => [
                'is_base' => $u['is_base'],
                'conversion_factor' => $u['conversion_factor'],
                'buy_price' => $u['buy_price'] ?? $product->buy_price,
                'sell_price' => $u['sell_price'] ?? $product->sell_price,
                'barcode' => $u['barcode'] ?? null,
            ]);

        if ($rows->isEmpty()) {
            // ponytail: no units sent — only seed default PCS base when product has none (matches docs/features/unit-conversion.md)
            if ($product->units()->exists()) {
                return;
            }

            $pcs = Unit::where('code', 'PCS')->first();
            if ($pcs) {
                $product->units()->attach($pcs->id, [
                    'is_base' => true,
                    'conversion_factor' => 1,
                    'buy_price' => $product->buy_price,
                    'sell_price' => $product->sell_price,
                ]);
            }

            return;
        }

        if (! $rows->contains(fn ($r) => $r['is_base'])) {
            // ponytail: no explicit base unit sent — treat first row as base with factor 1
            $first = $rows->keys()->first();
            $rows[$first] = ['is_base' => true, 'conversion_factor' => 1] + $rows[$first];
        }

        $product->units()->sync($rows->all());
    }

    private function logProductUpdate(Product $product, array $before): void
    {
        $after = $this->productAuditPayload($product->fresh());

        $this->auditLogService->log(
            event: 'product.updated',
            module: 'products',
            auditable: $product,
            description: 'Data produk diperbarui.',
            before: $before,
            after: $after
        );

        if (
            (int) $before['buy_price'] !== (int) $after['buy_price']
            || (int) $before['sell_price'] !== (int) $after['sell_price']
        ) {
            $this->auditLogService->log(
                event: 'product.price_updated',
                module: 'products',
                auditable: $product,
                description: 'Harga produk diperbarui.',
                before: [
                    'buy_price' => $before['buy_price'],
                    'sell_price' => $before['sell_price'],
                ],
                after: [
                    'buy_price' => $after['buy_price'],
                    'sell_price' => $after['sell_price'],
                ]
            );
        }
    }

    private function productAuditPayload(Product $product): array
    {
        return $this->auditLogService->only($product->toArray(), [
            'title',
            'barcode',
            'sku',
            'buy_price',
            'sell_price',
            'stock',
            'category_id',
        ]);
    }
}
