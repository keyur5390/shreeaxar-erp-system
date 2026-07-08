<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->uuid('id')->primary();
            $table->string('company_name', 300);
            $table->string('email', 255)->nullable()->unique();
            $table->string('secondary_email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('secondary_contact')->nullable();
            $table->string('tin_number', 100)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['company_name', 'is_active']);
            $table->index('tin_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
