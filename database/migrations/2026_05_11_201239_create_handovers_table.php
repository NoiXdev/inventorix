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
        Schema::create('handovers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('recipient_kind');
            $table->foreignUuid('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_email')->nullable();
            $table->text('accessories')->nullable();
            $table->text('condition_notes')->nullable();
            $table->text('terms_text');
            $table->string('signature_path');
            $table->string('signature_ip')->nullable();
            $table->string('signature_user_agent', 512)->nullable();
            $table->string('pdf_path')->nullable();
            $table->dateTime('signed_at');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('email_sent_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'signed_at']);
            $table->index('recipient_kind');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handovers');
    }
};
