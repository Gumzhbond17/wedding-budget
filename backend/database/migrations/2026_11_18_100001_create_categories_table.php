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
        // One table for revenue and expense categories, split by `type`.
        // wedding_id NULL = seeded system default (read-only, shared by all weddings).
        // wedding_id set  = custom category visible only inside that wedding.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 10); // App\Enums\CategoryType: revenue | expense
            $table->string('name', 100);
            $table->timestamps();

            // Blocks duplicate custom names inside a wedding. MySQL treats NULLs as distinct,
            // so system defaults are kept unique by the seeder (updateOrCreate), not this index.
            $table->unique(['wedding_id', 'type', 'name']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
