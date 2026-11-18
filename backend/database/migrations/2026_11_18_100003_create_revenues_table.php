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
        Schema::create('revenues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('category_id');
            $table->string('source', 150);

            // Rate snapshot taken on save. exchange_rate and base_amount are computed by the
            // server only: base_amount = amount * exchange_rate, in the wedding's base_currency.
            $table->decimal('amount', 20, 2);
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 20, 8);
            $table->decimal('base_amount', 20, 2);

            $table->string('status', 20)->default('expected'); // App\Enums\RevenueStatus
            $table->date('expected_date')->nullable();
            $table->date('received_date')->nullable();
            $table->string('payment_method', 20)->nullable(); // App\Enums\PaymentMethod
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Composite FK: the category must belong to the same wedding (DB-level IDOR guard).
            // RESTRICT: deleting a category in use fails (error 1451, mapped to 409 by
            // App\Actions\Categories\DeleteCategory) instead of destroying financial records.
            // Side effect: a plain DELETE of the wedding fails too, because InnoDB may reach
            // categories before revenues; App\Actions\Weddings\DeleteWedding deletes in safe order.
            $table->foreign(['wedding_id', 'category_id'])
                ->references(['wedding_id', 'id'])->on('categories')
                ->restrictOnDelete();

            // Every query is scoped to one wedding, so composite indexes lead with wedding_id.
            $table->index(['wedding_id', 'status']);
            $table->index(['wedding_id', 'received_date']);
            $table->index(['wedding_id', 'currency']);
        });

        DB::statement('ALTER TABLE revenues
            ADD CONSTRAINT revenues_amount_check CHECK (amount >= 0),
            ADD CONSTRAINT revenues_exchange_rate_check CHECK (exchange_rate > 0),
            ADD CONSTRAINT revenues_base_amount_check CHECK (base_amount >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenues');
    }
};
