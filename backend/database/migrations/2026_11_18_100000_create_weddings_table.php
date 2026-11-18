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
        Schema::create('weddings', function (Blueprint $table) {
            $table->id();
            // RESTRICT: removing an account must go through App\Actions\Weddings\DeleteWedding per
            // wedding, never a silent DB cascade through financial records.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->date('wedding_date')->nullable();
            $table->string('status', 20)->default('planning'); // App\Enums\WeddingStatus: planning | completed
            // ISO 4217 code backed by the App\Enums\Currency enum. Locked once revenue or expenses
            // exist, because every snapshotted base_* amount is expressed in this currency.
            $table->char('base_currency', 3)->default('LAK');
            // DECIMAL(20,2), never FLOAT: LAK budgets reach hundreds of millions and must stay exact.
            $table->decimal('budget_target', 20, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            // "My weddings" list filtered by status; also backs the user_id foreign key.
            $table->index(['user_id', 'status']);
        });

        // Last line of defence behind Form Request validation (MySQL 8.0.16+ enforces CHECK).
        DB::statement('ALTER TABLE weddings
            ADD CONSTRAINT weddings_budget_target_check CHECK (budget_target >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weddings');
    }
};
