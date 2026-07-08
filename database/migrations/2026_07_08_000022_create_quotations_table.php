<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->uuid('id')->primary();
            $table->string('quotation_number', 30)->unique();
            $table->uuid('customer_id');
            $table->uuid('status_id');
            $table->date('quotation_date');
            $table->date('expiry_date');
            $table->uuid('authorized_by_id');
            $table->uuid('bank_detail_id')->nullable();
            $table->json('bank_snapshot')->nullable();
            $table->longText('terms_conditions')->nullable();
            $table->longText('notes')->nullable();
            $table->decimal('sub_total', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->integer('revision_number')->default(1);
            $table->timestamp('last_modified_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status_id']);
            $table->index(['quotation_date', 'expiry_date']);
            $table->index('authorized_by_id');
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('status_id')->references('id')->on('quotation_statuses')->restrictOnDelete();
            $table->foreign('authorized_by_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('bank_detail_id')->references('id')->on('bank_details')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
