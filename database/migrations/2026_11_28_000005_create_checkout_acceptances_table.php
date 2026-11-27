<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Acceptances of checked-out items (Snipe-IT's checkout_acceptances): the
     * user confirms or declines through a signed link in an email, no login
     * needed. The item name and the EULA are kept as they were sent.
     */
    public function up(): void
    {
        Schema::create('checkout_acceptances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('item_type', 32);
            $table->uuid('item_id');
            // The accessory checkout, license seat or consumable assignment, if any.
            $table->uuid('checkout_id')->nullable();
            $table->string('item_name', 191);
            $table->text('eula_text')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->text('note')->nullable();
            $table->string('signature_path', 191)->nullable();
            $table->string('response_ip', 45)->nullable();
            $table->text('response_user_agent')->nullable();
            $table->timestamps();
            $table->creator();
            $table->index(['item_type', 'item_id'], 'checkout_acceptances_item_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_acceptances');
    }
};
