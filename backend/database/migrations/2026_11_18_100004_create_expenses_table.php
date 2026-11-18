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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('category_id');
            $table->string('description', 255);
            // Plain text by design: vendors as entities are out of MVP scope.
            $table->string('vendor_name', 150)->nullable();

            // One rate snapshot for the estimate and the actual so they stay comparable.
            // Computed by the server only: base_x = x * exchange_rate, in the wedding's base_currency.
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 20, 8);
            $table->decimal('estimated_amount', 20, 2);
            // NULL = not invoiced yet. Kept distinct from 0 so "actual" totals only count known costs.
            $table->decimal('actual_amount', 20, 2)->nullable();
            $table->decimal('base_estimated_amount', 20, 2);
            $table->decimal('base_actual_amount', 20, 2)->nullable();

            // Cached sums of expense_payments, rewritten in the same DB transaction as any payment
            // change. paid_amount is in this expense's currency (payments must match it);
            // base_paid_amount sums each payment's own base_amount, i.e. cash out at payment-day rates.
            $table->decimal('paid_amount', 20, 2)->default(0);
            $table->decimal('base_paid_amount', 20, 2)->default(0);

            // App\Enums\ExpenseStatus. planned | booked | cancelled are chosen by the user;
            // partially_paid | paid are derived from paid_amount vs actual_amount.
            $table->string('status', 20)->default('planned');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Composite FK: the category must belong to the same wedding (DB-level IDOR guard).
            // RESTRICT: deleting a category in use fails (error 1451, mapped to 409 by
            // App\Actions\Categories\DeleteCategory) instead of destroying financial records.
            // Side effect: a plain DELETE of the wedding fails too, because InnoDB may reach
            // categories before expenses; App\Actions\Weddings\DeleteWedding deletes in safe order.
            $table->foreign(['wedding_id', 'category_id'])
                ->references(['wedding_id', 'id'])->on('categories')
                ->restrictOnDelete();

            // Target of the composite (expense_id, currency) FK on expense_payments.
            $table->unique(['id', 'currency']);

            // Every query is scoped to one wedding, so composite indexes lead with wedding_id.
            $table->index(['wedding_id', 'status']);
            $table->index(['wedding_id', 'due_date']);
            $table->index(['wedding_id', 'currency']);
        });

        DB::statement('ALTER TABLE expenses
            ADD CONSTRAINT expenses_exchange_rate_check CHECK (exchange_rate > 0),
            ADD CONSTRAINT expenses_estimated_amount_check CHECK (estimated_amount >= 0),
            ADD CONSTRAINT expenses_actual_amount_check CHECK (actual_amount IS NULL OR actual_amount >= 0),
            ADD CONSTRAINT expenses_paid_amount_check CHECK (paid_amount >= 0),
            ADD CONSTRAINT expenses_base_estimated_amount_check CHECK (base_estimated_amount >= 0),
            ADD CONSTRAINT expenses_base_actual_amount_check CHECK (base_actual_amount IS NULL OR base_actual_amount >= 0),
            ADD CONSTRAINT expenses_base_paid_amount_check CHECK (base_paid_amount >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
