<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->uuid('id')->primary();
            $table->string('code', 3)->unique();
            $table->string('name', 100);
            $table->string('symbol', 10);
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $rwfId = (string) Str::uuid();

        DB::table('currencies')->insert([
            'id' => $rwfId,
            'code' => 'RWF',
            'name' => 'Rwandan Franc',
            'symbol' => 'RWF',
            'decimal_places' => 0,
            'exchange_rate' => 1,
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('products', function (Blueprint $table) use ($rwfId): void {
            $table->uuid('currency_id')->default($rwfId)->after('rate');
            $table->foreign('currency_id')->references('id')->on('currencies')->restrictOnDelete();
        });

        Schema::table('quotations', function (Blueprint $table) use ($rwfId): void {
            $table->uuid('currency_id')->default($rwfId)->after('total_amount');
            $table->decimal('exchange_rate', 18, 8)->default(1)->after('currency_id');
            $table->json('currency_snapshot')->nullable()->after('exchange_rate');
            $table->foreign('currency_id')->references('id')->on('currencies')->restrictOnDelete();
        });

        DB::table('quotations')->whereNull('currency_snapshot')->update([
            'currency_snapshot' => json_encode([
                'code' => 'RWF',
                'symbol' => 'RWF',
                'decimal_places' => 0,
                'exchange_rate' => 1,
            ]),
        ]);
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropForeign(['currency_id']);
            $table->dropColumn(['currency_id', 'exchange_rate', 'currency_snapshot']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['currency_id']);
            $table->dropColumn('currency_id');
        });

        Schema::dropIfExists('currencies');
    }
};
