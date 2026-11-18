<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            // Restrict: a category still in use cannot be deleted out from under existing records.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('description', 255);
            // Plain text by design: vendors as entities are out of MVP scope.
            $table->string('vendor_name', 150)->nullable();

            // One rate snapshot applies to all three amounts so they stay comparable.
            // Computed by the server only: base_x = x * exchange_rate, in the wedding's base_currency.
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 20, 8);
            $table->decimal('estimated_amount', 20, 2);
            // NULL = not invoiced yet. Kept distinct from 0 so "actual" totals only count known costs.
            $table->decimal('actual_amount', 20, 2)->nullable();
            $table->decimal('paid_amount', 20, 2)->default(0);
            $table->decimal('base_estimated_amount', 20, 2);
            $table->decimal('base_actual_amount', 20, 2)->nullable();
            $table->decimal('base_paid_amount', 20, 2)->default(0);

            $table->string('status', 20)->default('planned'); // App\Enums\ExpenseStatus
            $table->date('due_date')->nullable();
            // Date of the latest payment; drives the "expenses out" line of the monthly cash-flow chart.
            $table->date('paid_date')->nullable();
            $table->text('notes')->nullable();

            // Single optional receipt on the private disk. Columns instead of a separate table
            // because the MVP allows exactly one file per expense.
            $table->string('receipt_path')->nullable();
            $table->string('receipt_original_name')->nullable();
            $table->string('receipt_mime_type', 100)->nullable();
            $table->unsignedInteger('receipt_size')->nullable(); // bytes

            $table->timestamps();
            $table->softDeletes();

            // Every query is scoped to one wedding, so composite indexes lead with wedding_id.
            // MySQL reuses the first one to back the wedding_id foreign key.
            $table->index(['wedding_id', 'status']);
            $table->index(['wedding_id', 'due_date']);
            $table->index(['wedding_id', 'paid_date']);
            $table->index(['wedding_id', 'currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
