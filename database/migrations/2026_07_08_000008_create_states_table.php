<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->uuid('country_id');
            $table->timestamps();
            $table->unique(['name', 'country_id']);
            $table->foreign('country_id')->references('id')->on('countries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};
