<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pto_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('employee_id');
            $table->string('type', 20); // VACATION, SICK, PERSONAL
            $table->decimal('balance_hours', 8, 2)->default(0);
            $table->decimal('accrued_hours', 8, 2)->default(0);
            $table->decimal('used_hours', 8, 2)->default(0);
            $table->integer('year');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index('tenant_id');
            $table->unique(['employee_id', 'type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pto_balances');
    }
};
