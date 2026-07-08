<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->uuid('user_id');
            $table->uuid('address_id');
            $table->primary(['user_id', 'address_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('address_id')->references('id')->on('addresses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
