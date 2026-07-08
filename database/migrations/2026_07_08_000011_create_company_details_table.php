<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_details', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->string('id', 36)->primary()->default('singleton');
            $table->string('name', 255);
            $table->string('logo', 500)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('tin_number', 100)->nullable();
            $table->string('vat_number', 100)->nullable();
            $table->string('website', 255)->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_details');
    }
};
