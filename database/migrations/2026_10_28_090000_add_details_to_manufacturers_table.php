<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacturers', function (Blueprint $table) {
            $table->string('url')->nullable()->after('name');
            $table->string('support_url')->nullable()->after('url');
            $table->string('warranty_lookup_url')->nullable()->after('support_url');
            $table->string('support_phone')->nullable()->after('warranty_lookup_url');
            $table->string('support_email')->nullable()->after('support_phone');
            // Holds a storage path, not an image — named for what it contains,
            // matching attachments.path.
            $table->string('image_path')->nullable()->after('support_email');
            $table->text('notes')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('manufacturers', function (Blueprint $table) {
            $table->dropColumn([
                'url', 'support_url', 'warranty_lookup_url',
                'support_phone', 'support_email', 'image_path', 'notes',
            ]);
        });
    }
};
