<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('employee_id');
            $table->uuid('job_id');
            $table->uuid('team_id'); // team at time of entry
            $table->timestamp('clock_in');
            $table->timestamp('clock_out')->nullable();
            $table->decimal('clock_in_lat', 10, 7)->nullable();
            $table->decimal('clock_in_lng', 10, 7)->nullable();
            $table->decimal('clock_out_lat', 10, 7)->nullable();
            $table->decimal('clock_out_lng', 10, 7)->nullable();
            $table->string('clock_method', 20); // GEOFENCE, MANUAL, KIOSK, ADMIN_OVERRIDE
            $table->decimal('total_hours', 5, 2)->nullable();
            $table->decimal('overtime_hours', 5, 2)->nullable();
            $table->string('status', 25)->default('ACTIVE'); // ACTIVE, SUBMITTED, APPROVED, REJECTED, PAYROLL_PROCESSED
            $table->string('sync_status', 20)->default('SYNCED'); // PENDING, SYNCED, CONFLICT
            $table->string('device_id', 255)->nullable();
            $table->text('selfie_url')->nullable();
            $table->string('verification_status', 20)->default('NOT_REQUIRED'); // VERIFIED, UNVERIFIED, NOT_REQUIRED
            $table->text('notes')->nullable();
            $table->timestamps();

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->foreign('job_id')->references('id')->on('job_sites')->onDelete('cascade');
                $table->foreign('team_id')->references('id')->on('teams');
            }
            $table->index('tenant_id');
            $table->index(['employee_id', 'clock_in']);
            $table->index(['employee_id', 'status']);
            $table->index(['job_id', 'clock_in']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
