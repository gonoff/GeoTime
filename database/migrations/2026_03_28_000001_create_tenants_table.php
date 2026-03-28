<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('timezone', 50)->default('America/New_York');
            $table->tinyInteger('workweek_start_day')->default(1);
            $table->json('overtime_rule')->default('{"weekly_threshold": 40, "daily_threshold": null, "multiplier": 1.5}');
            $table->string('rounding_rule', 20)->default('EXACT');
            $table->string('clock_verification_mode', 20)->default('AUTO_ONLY');
            $table->string('plan', 20)->default('starter');
            $table->string('status', 20)->default('trial');
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
