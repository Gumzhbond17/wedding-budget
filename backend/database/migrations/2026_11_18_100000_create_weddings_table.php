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
        Schema::create('weddings', function (Blueprint $table) {
            $table->id();
            // Every wedding-scoped request is authorized through the owner, so this FK index
            // doubles as the ownership filter for "list my weddings".
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->date('wedding_date')->nullable();
            // ISO 4217 code backed by the App\Enums\Currency enum. Locked once revenue or expenses
            // exist, because every snapshotted base_* amount is expressed in this currency.
            $table->char('base_currency', 3)->default('LAK');
            // DECIMAL(20,2), never FLOAT: LAK budgets reach hundreds of millions and must stay exact.
            $table->decimal('budget_target', 20, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weddings');
    }
};
