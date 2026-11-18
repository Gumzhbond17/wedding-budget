<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Manually managed rates, owned per wedding. Meaning: 1 from_currency = rate to_currency.
        // Revenue, expense and payment rows never reference this table; they snapshot the rate on
        // save, so editing or deleting a rate here can never change historical amounts.
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->char('from_currency', 3);
            $table->char('to_currency', 3);
            // 8 decimals keeps small inverse rates precise (e.g. 1 LAK = 0.00004600 USD).
            $table->decimal('rate', 20, 8);
            $table->date('effective_date');
            $table->timestamps();

            // One rate per pair per day. The same index serves the lookup
            // "latest rate for (wedding, from, to) with effective_date <= :date" as a range scan.
            $table->unique(
                ['wedding_id', 'from_currency', 'to_currency', 'effective_date'],
                'exchange_rates_pair_date_unique',
            );
        });

        DB::statement('ALTER TABLE exchange_rates
            ADD CONSTRAINT exchange_rates_rate_check CHECK (rate > 0),
            ADD CONSTRAINT exchange_rates_pair_check CHECK (from_currency <> to_currency)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
