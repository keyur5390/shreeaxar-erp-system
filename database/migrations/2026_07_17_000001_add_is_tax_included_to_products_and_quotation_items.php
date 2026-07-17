<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_tax_included')->default(false)->after('rate');
        });

        Schema::table('quotation_items', function (Blueprint $table): void {
            $table->boolean('is_tax_included')->default(false)->after('discount_rate');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table): void {
            $table->dropColumn('is_tax_included');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('is_tax_included');
        });
    }
};
