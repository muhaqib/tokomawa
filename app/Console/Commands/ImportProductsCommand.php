<?php

namespace App\Console\Commands;

use App\Imports\ProductsImport;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ImportProductsCommand extends Command
{
    protected $signature = 'products:import {file : Path file Excel, relatif terhadap direktori aplikasi atau absolut}
        {--warehouse= : Kode gudang aktif tujuan, misalnya PUSAT}
        {--dry-run : Validasi dan simulasikan impor tanpa menyimpan perubahan}
        {--force : Jalankan tanpa konfirmasi}';

    protected $description = 'Impor master produk Excel beserta satuan, harga, kategori, dan stok gudang';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = base_path($path);
        }
        if (! is_file($path) || ! is_readable($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') {
            $this->error('File harus berupa .xlsx yang ada dan dapat dibaca.');

            return self::FAILURE;
        }
        if (! $this->option('warehouse')) {
            $this->error('Tentukan kode gudang tujuan dengan --warehouse=PUSAT.');

            return self::FAILURE;
        }
        $warehouse = Warehouse::where('code', $this->option('warehouse'))->where('is_active', true)->first();
        if (! $warehouse) {
            $this->error('Kode gudang tidak ditemukan atau gudang tidak aktif.');

            return self::FAILURE;
        }

        $this->info("Gudang tujuan: {$warehouse->code} — {$warehouse->name}");
        $this->warn('Produk yang cocok akan diperbarui. STOK terisi mengganti saldo gudang tujuan; STOK kosong mempertahankan saldo produk lama.');
        if (! $this->option('dry-run') && ! $this->option('force') && ! $this->confirm('Lanjutkan impor?', false)) {
            $this->info('Impor dibatalkan.');

            return self::FAILURE;
        }

        $import = new ProductsImport($warehouse->id);
        // The outer transaction also covers multiple worksheets and dry-run rollback.
        $level = DB::transactionLevel();
        DB::beginTransaction();
        try {
            Excel::import($import, $path);
            if ($import->getRowCount() === 0) {
                throw ValidationException::withMessages(['file' => 'File tidak berisi baris produk.']);
            }
            if ($this->option('dry-run')) {
                DB::rollBack();
                $this->info("Simulasi berhasil: {$import->getRowCount()} baris produk/satuan valid. Tidak ada perubahan disimpan.");
            } else {
                DB::commit();
                $this->info("Impor selesai: {$import->getRowCount()} baris produk/satuan diproses.");
            }
        } catch (Throwable $e) {
            DB::rollBack($level);
            $this->error('Impor gagal. Semua perubahan impor dibatalkan.');
            if ($e instanceof ValidationException) {
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $this->error("{$field}: {$message}");
                    }
                }
            } else {
                report($e);
                $this->error($e->getMessage());
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
