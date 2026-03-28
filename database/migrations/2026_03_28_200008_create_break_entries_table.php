<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('break_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('time_entry_id');
            $table->string('type', 20); // PAID_REST, UNPAID_MEAL
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->boolean('was_interrupted')->default(false);
            $table->timestamps();

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('time_entry_id')->references('id')->on('time_entries')->onDelete('cascade');
            }
            $table->index('tenant_id');
            $table->index('time_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('break_entries');
    }
};
