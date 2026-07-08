<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_status_histories', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->uuid('id')->primary();
            $table->uuid('quotation_id');
            $table->uuid('from_status_id')->nullable();
            $table->uuid('to_status_id');
            $table->uuid('changed_by_id');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->foreign('quotation_id')->references('id')->on('quotations')->cascadeOnDelete();
            $table->foreign('from_status_id')->references('id')->on('quotation_statuses')->nullOnDelete();
            $table->foreign('to_status_id')->references('id')->on('quotation_statuses')->restrictOnDelete();
            $table->foreign('changed_by_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_status_histories');
    }
};
