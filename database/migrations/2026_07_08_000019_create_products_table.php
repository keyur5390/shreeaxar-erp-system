<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->uuid('id')->primary();
            $table->string('title', 300);
            $table->decimal('rate', 15, 2);
            $table->uuid('unit_id');
            $table->string('model_number', 200)->nullable();
            $table->longText('description')->nullable();
            $table->string('primary_image', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['title', 'is_active']);
            $table->index('model_number');
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
