<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->uuid('id')->primary();
            $table->uuid('quotation_id');
            $table->uuid('product_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->longText('description')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('rate', 15, 2);
            $table->integer('quantity');
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
            $table->index('quotation_id');
            $table->foreign('quotation_id')->references('id')->on('quotations')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
    }
};
