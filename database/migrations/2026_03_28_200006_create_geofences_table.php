<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('job_id');
            $table->string('name', 100);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->integer('radius_meters')->default(100); // 50-500
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('job_id')->references('id')->on('job_sites')->onDelete('cascade');
            }
            $table->index('tenant_id');
            $table->index(['job_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofences');
    }
};
