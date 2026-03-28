<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('color_tag', 7)->nullable(); // Hex color
            $table->uuid('lead_employee_id')->nullable();
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE, ARCHIVED
            $table->timestamps();

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            }
            $table->index('tenant_id');
        });

        // Add foreign key for current_team_id on employees now that teams table exists
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreign('current_team_id')->references('id')->on('teams')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropForeign(['current_team_id']);
            });
        }
        Schema::dropIfExists('teams');
    }
};
