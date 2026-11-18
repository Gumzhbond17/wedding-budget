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
        Schema::create('revenues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            // Restrict: a category still in use cannot be deleted out from under existing records.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('source', 150);

            // Rate snapshot taken on save. exchange_rate and base_amount are computed by the
            // server only: base_amount = amount * exchange_rate, in the wedding's base_currency.
            $table->decimal('amount', 20, 2);
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 20, 8);
            $table->decimal('base_amount', 20, 2);

            $table->string('status', 20)->default('expected'); // App\Enums\RevenueStatus
            $table->date('received_date')->nullable();
            $table->string('payment_method', 20)->nullable(); // App\Enums\PaymentMethod
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Every query is scoped to one wedding, so composite indexes lead with wedding_id.
            // MySQL reuses the first one to back the wedding_id foreign key.
            $table->index(['wedding_id', 'status']);
            $table->index(['wedding_id', 'received_date']);
            $table->index(['wedding_id', 'currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenues');
    }
};
