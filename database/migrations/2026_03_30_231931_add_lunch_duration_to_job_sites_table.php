<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_sites', function (Blueprint $table) {
            $table->unsignedSmallInteger('lunch_duration_minutes')->nullable()->after('status');
            $table->decimal('lunch_after_hours', 4, 2)->nullable()->after('lunch_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('job_sites', function (Blueprint $table) {
            $table->dropColumn(['lunch_duration_minutes', 'lunch_after_hours']);
        });
    }
};
