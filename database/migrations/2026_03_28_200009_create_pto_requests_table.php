<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pto_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('employee_id');
            $table->string('type', 20); // VACATION, SICK, PERSONAL, UNPAID
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('hours', 6, 2);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('PENDING'); // PENDING, APPROVED, DENIED, CANCELLED
            $table->uuid('reviewed_by')->nullable();
            $table->text('review_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index('tenant_id');
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pto_requests');
    }
};
