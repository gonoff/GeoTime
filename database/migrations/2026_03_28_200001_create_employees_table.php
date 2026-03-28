<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('current_team_id')->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255);
            $table->string('phone', 20)->nullable();
            $table->string('role', 20)->default('EMPLOYEE');
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->text('ssn_encrypted')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->json('address')->nullable();
            $table->date('hire_date');
            $table->string('device_id', 255)->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->string('qbo_employee_id', 50)->nullable();
            $table->timestamps();

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            }
            $table->index('tenant_id');
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
