<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('unit_name')->default('PCS');
            $table->decimal('conversion', 18, 4)->default(1);
            $table->string('department')->nullable();
            $table->string('sub_category')->nullable();
            $table->decimal('profit_percent', 18, 4)->nullable();
            $table->string('rack')->nullable();
            $table->string('master_key', 64)->nullable()->unique();
            $table->decimal('buy_price', 20, 4)->change();
            $table->decimal('sell_price', 20, 4)->change();
        });
        Schema::table('product_units', function (Blueprint $table) {
            $table->json('master_data')->nullable();
            $table->decimal('buy_price', 20, 4)->change();
            $table->decimal('sell_price', 20, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_units', fn (Blueprint $table) => $table->dropColumn('master_data'));
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['unit_name', 'conversion', 'department', 'sub_category', 'profit_percent', 'rack', 'master_key']);
        });
    }
};
