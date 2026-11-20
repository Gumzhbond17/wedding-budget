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
        // Money guests put in their invitation card envelope. Always in the wedding's
        // base_currency (no currency or rate columns): base_currency is locked once any
        // envelope exists, so the amount never needs converting and counts as received revenue.
        Schema::create('gift_envelopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('full_name', 150);
            $table->decimal('amount', 20, 2);
            $table->string('guest_group', 20); // App\Enums\GuestGroup
            $table->date('received_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Every query is scoped to one wedding, so composite indexes lead with wedding_id.
            $table->index(['wedding_id', 'guest_group']);
            $table->index(['wedding_id', 'received_date']);
            $table->index(['wedding_id', 'full_name']);
        });

        DB::statement('ALTER TABLE gift_envelopes
            ADD CONSTRAINT gift_envelopes_amount_check CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gift_envelopes');
    }
};
