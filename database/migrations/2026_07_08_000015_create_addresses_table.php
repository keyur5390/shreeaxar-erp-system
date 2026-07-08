<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->uuid('id')->primary();
            $table->uuid('address_type_id')->nullable();
            $table->string('address_line_1', 500);
            $table->string('address_line_2', 500)->nullable();
            $table->uuid('country_id');
            $table->uuid('state_id')->nullable();
            $table->string('city', 150)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->timestamps();
            $table->foreign('address_type_id')->references('id')->on('address_types')->nullOnDelete();
            $table->foreign('country_id')->references('id')->on('countries')->restrictOnDelete();
            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
