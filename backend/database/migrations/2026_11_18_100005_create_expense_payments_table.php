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
        // Individual payments against an expense. Source of truth for paid_amount,
        // base_paid_amount and the derived partially_paid / paid status on expenses.
        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_id');

            // Own rate snapshot: base_amount = amount * exchange_rate at the time of payment,
            // so the cash-flow chart shows what actually left in base currency.
            $table->decimal('amount', 20, 2);
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 20, 8);
            $table->decimal('base_amount', 20, 2);

            $table->date('paid_date');
            $table->string('payment_method', 20)->nullable(); // App\Enums\PaymentMethod
            $table->text('notes')->nullable();

            // Single optional receipt on the private disk, served only via temporary signed URLs.
            $table->string('receipt_path')->nullable();
            $table->string('receipt_original_name')->nullable();
            $table->string('receipt_mime_type', 100)->nullable();
            $table->unsignedInteger('receipt_size')->nullable(); // bytes

            $table->timestamps();

            // Composite FK forces the payment currency to equal its expense's currency, so
            // paid_amount is a plain SUM(amount). ON UPDATE RESTRICT also blocks changing an
            // expense's currency once payments exist.
            $table->foreign(['expense_id', 'currency'])
                ->references(['id', 'currency'])->on('expenses')
                ->cascadeOnDelete()
                ->restrictOnUpdate();

            // Backs the FK above (leading expense_id) and the monthly cash-flow GROUP BY.
            $table->index(['expense_id', 'paid_date']);
        });

        DB::statement('ALTER TABLE expense_payments
            ADD CONSTRAINT expense_payments_amount_check CHECK (amount > 0),
            ADD CONSTRAINT expense_payments_exchange_rate_check CHECK (exchange_rate > 0),
            ADD CONSTRAINT expense_payments_base_amount_check CHECK (base_amount >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
    }
};
