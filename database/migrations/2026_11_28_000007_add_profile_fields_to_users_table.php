<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Snipe-IT style profile fields on users (department_id came with the departments table). */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_num', 191)->nullable()->index();
            $table->string('jobtitle', 255)->nullable();
            $table->foreignUuid('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('location_id');
            $table->dropIndex(['employee_num']);
            $table->dropColumn(['employee_num', 'jobtitle', 'phone', 'mobile', 'start_date', 'end_date', 'notes']);
        });
    }
};
