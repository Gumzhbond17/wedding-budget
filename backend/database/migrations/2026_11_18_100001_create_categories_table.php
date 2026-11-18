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
        // Revenue and expense categories, split by `type`, always owned by one wedding.
        // Default names live in code and are copied into each new wedding, so users can rename
        // or remove them like any custom category and every row has a single owner to authorize.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // App\Enums\CategoryType: revenue | expense
            $table->string('name', 100);
            // Per-category budget in the wedding's base currency. Expense categories only;
            // drives "Budget vs Actual" and the over-budget highlight.
            $table->decimal('planned_amount', 20, 2)->nullable();
            $table->timestamps();

            $table->unique(['wedding_id', 'type', 'name']);
            // Target of the composite (wedding_id, category_id) foreign keys on revenues and
            // expenses, which guarantee a record can only use a category of its own wedding.
            $table->unique(['wedding_id', 'id']);
        });

        DB::statement("ALTER TABLE categories
            ADD CONSTRAINT categories_planned_amount_check CHECK (planned_amount IS NULL OR planned_amount >= 0),
            ADD CONSTRAINT categories_planned_amount_type_check CHECK (planned_amount IS NULL OR type = 'expense')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
