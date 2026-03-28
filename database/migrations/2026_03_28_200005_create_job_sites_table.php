<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name', 255);
            $table->string('client_name', 255)->nullable();
            $table->string('qbo_customer_id', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE, COMPLETED, ON_HOLD
            $table->decimal('budget_hours', 10, 2)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            }
            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('job_team_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('job_site_id');
            $table->uuid('team_id');
            $table->date('assigned_date');
            $table->date('removed_date')->nullable();
            $table->timestamps();

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('job_site_id')->references('id')->on('job_sites')->onDelete('cascade');
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            }
            $table->index('tenant_id');
            $table->index(['job_site_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_team_assignments');
        Schema::dropIfExists('job_sites');
    }
};
