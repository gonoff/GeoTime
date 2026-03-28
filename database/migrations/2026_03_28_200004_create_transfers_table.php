<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('employee_id');
            $table->uuid('from_team_id');
            $table->uuid('to_team_id');
            $table->string('reason_category', 30); // OPERATIONAL, PERFORMANCE, EMPLOYEE_REQUEST, ADMINISTRATIVE
            $table->string('reason_code', 30); // WORKLOAD_BALANCE, SKILL_MATCH, PROJECT_NEED, etc.
            $table->text('notes')->nullable();
            $table->string('transfer_type', 20); // PERMANENT, TEMPORARY
            $table->date('effective_date');
            $table->date('expected_return_date')->nullable();
            $table->uuid('initiated_by');
            $table->uuid('approved_by')->nullable();
            $table->string('status', 20)->default('PENDING'); // PENDING, APPROVED, REJECTED, COMPLETED, REVERTED
            $table->timestamps();

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->foreign('from_team_id')->references('id')->on('teams');
                $table->foreign('to_team_id')->references('id')->on('teams');
            }
            $table->index('tenant_id');
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
