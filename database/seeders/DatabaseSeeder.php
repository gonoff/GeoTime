<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Geofence;
use App\Models\Job;
use App\Models\PtoBalance;
use App\Models\PtoRequest;
use App\Models\Team;
use App\Models\TeamAssignment;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\Transfer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Tenant
        $tenant = Tenant::create([
            'name' => 'Apex Construction',
            'plan' => 'professional',
            'status' => 'active',
            'timezone' => 'America/New_York',
            'workweek_start_day' => 1,
            'clock_verification_mode' => 'AUTO_ONLY',
            'overtime_rule' => ['weekly_threshold' => 40, 'daily_threshold' => null, 'multiplier' => 1.5],
            'rounding_rule' => 'QUARTER',
        ]);

        // Bind tenant so BelongsToTenant trait works
        app()->instance('current_tenant', $tenant);

        // Admin user
        $admin = User::create([
            'name' => 'Mike Reynolds',
            'email' => 'admin@demo.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        // Manager user
        User::create([
            'name' => 'Sarah Chen',
            'email' => 'manager@demo.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'manager',
        ]);

        // Teams
        $teamData = [
            ['name' => 'Framing Crew', 'color_tag' => '#3B82F6', 'status' => 'ACTIVE'],
            ['name' => 'Electrical', 'color_tag' => '#F59E0B', 'status' => 'ACTIVE'],
            ['name' => 'Plumbing', 'color_tag' => '#10B981', 'status' => 'ACTIVE'],
            ['name' => 'Finishing', 'color_tag' => '#8B5CF6', 'status' => 'ACTIVE'],
        ];

        $teams = [];
        foreach ($teamData as $td) {
            $teams[] = Team::create(array_merge($td, ['tenant_id' => $tenant->id]));
        }

        // Employees
        $employeeData = [
            ['first_name' => 'Carlos', 'last_name' => 'Martinez', 'email' => 'carlos@apex.com', 'phone' => '555-0101', 'hourly_rate' => 28.00, 'hire_date' => '2023-03-15', 'address' => ['street' => '42 Elm St', 'city' => 'Hartford', 'state' => 'CT', 'zip' => '06103']],
            ['first_name' => 'James', 'last_name' => 'Wilson', 'email' => 'james@apex.com', 'phone' => '555-0102', 'hourly_rate' => 32.00, 'hire_date' => '2022-08-01', 'address' => ['street' => '118 Park Ave', 'city' => 'West Hartford', 'state' => 'CT', 'zip' => '06119']],
            ['first_name' => 'Maria', 'last_name' => 'Rodriguez', 'email' => 'maria@apex.com', 'phone' => '555-0103', 'hourly_rate' => 26.50, 'hire_date' => '2024-01-10', 'address' => ['street' => '305 Franklin St', 'city' => 'New Haven', 'state' => 'CT', 'zip' => '06511']],
            ['first_name' => 'David', 'last_name' => 'Kim', 'email' => 'david@apex.com', 'phone' => '555-0104', 'hourly_rate' => 30.00, 'hire_date' => '2023-06-20', 'address' => ['street' => '77 Broad St', 'city' => 'Stamford', 'state' => 'CT', 'zip' => '06901']],
            ['first_name' => 'Lisa', 'last_name' => 'Thompson', 'email' => 'lisa@apex.com', 'phone' => '555-0105', 'hourly_rate' => 27.00, 'hire_date' => '2023-11-01', 'address' => ['street' => '290 Main St', 'city' => 'Bridgeport', 'state' => 'CT', 'zip' => '06604']],
            ['first_name' => 'Robert', 'last_name' => 'Johnson', 'email' => 'robert@apex.com', 'phone' => '555-0106', 'hourly_rate' => 35.00, 'hire_date' => '2021-05-10', 'address' => ['street' => '55 Cherry Hill Dr', 'city' => 'Norwalk', 'state' => 'CT', 'zip' => '06851']],
            ['first_name' => 'Ana', 'last_name' => 'Gonzalez', 'email' => 'ana@apex.com', 'phone' => '555-0107', 'hourly_rate' => 25.00, 'hire_date' => '2024-06-15', 'address' => ['street' => '14 Oak Ln', 'city' => 'Waterbury', 'state' => 'CT', 'zip' => '06702']],
            ['first_name' => 'Kevin', 'last_name' => 'Brown', 'email' => 'kevin@apex.com', 'phone' => '555-0108', 'hourly_rate' => 29.00, 'hire_date' => '2023-09-01', 'address' => ['street' => '622 State St', 'city' => 'New Haven', 'state' => 'CT', 'zip' => '06510']],
            ['first_name' => 'Patricia', 'last_name' => 'Davis', 'email' => 'patricia@apex.com', 'phone' => '555-0109', 'hourly_rate' => 31.00, 'hire_date' => '2022-12-01', 'address' => ['street' => '180 Farmington Ave', 'city' => 'Hartford', 'state' => 'CT', 'zip' => '06105']],
            ['first_name' => 'Miguel', 'last_name' => 'Santos', 'email' => 'miguel@apex.com', 'phone' => '555-0110', 'hourly_rate' => 26.00, 'hire_date' => '2024-02-20', 'address' => ['street' => '33 River Rd', 'city' => 'Shelton', 'state' => 'CT', 'zip' => '06484']],
            ['first_name' => 'Jennifer', 'last_name' => 'Lee', 'email' => 'jennifer@apex.com', 'phone' => '555-0111', 'hourly_rate' => 28.50, 'hire_date' => '2023-07-12', 'address' => ['street' => '401 Post Rd', 'city' => 'Fairfield', 'state' => 'CT', 'zip' => '06824']],
            ['first_name' => 'Thomas', 'last_name' => 'Anderson', 'email' => 'thomas@apex.com', 'phone' => '555-0112', 'hourly_rate' => 33.00, 'hire_date' => '2022-03-05', 'address' => ['street' => '88 Whitney Ave', 'city' => 'Hamden', 'state' => 'CT', 'zip' => '06517']],
        ];

        $employees = [];
        foreach ($employeeData as $i => $ed) {
            $teamIndex = $i % count($teams);
            $emp = Employee::create(array_merge($ed, [
                'tenant_id' => $tenant->id,
                'current_team_id' => $teams[$teamIndex]->id,
                'role' => 'EMPLOYEE',
                'status' => 'ACTIVE',
            ]));
            $employees[] = $emp;

            // Team assignment history
            TeamAssignment::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $emp->id,
                'team_id' => $teams[$teamIndex]->id,
                'assigned_at' => Carbon::parse($ed['hire_date']),
                'assigned_by' => $admin->id,
            ]);
        }

        // Set team leads
        $teams[0]->update(['lead_employee_id' => $employees[0]->id]); // Carlos leads Framing
        $teams[1]->update(['lead_employee_id' => $employees[5]->id]); // Robert leads Electrical
        $teams[2]->update(['lead_employee_id' => $employees[2]->id]); // Maria leads Plumbing
        $teams[3]->update(['lead_employee_id' => $employees[11]->id]); // Thomas leads Finishing

        // Job Sites
        $jobData = [
            ['name' => 'Riverside Apartments', 'address' => '450 River Rd, Hartford, CT', 'status' => 'ACTIVE', 'start_date' => '2025-11-01', 'lunch_duration_minutes' => 30, 'lunch_after_hours' => 6],
            ['name' => 'Oak Street Renovation', 'address' => '112 Oak St, New Haven, CT', 'status' => 'ACTIVE', 'start_date' => '2026-01-15', 'lunch_duration_minutes' => 30, 'lunch_after_hours' => 6],
            ['name' => 'Downtown Office Build', 'address' => '88 Main St, Stamford, CT', 'status' => 'ACTIVE', 'start_date' => '2026-02-01', 'lunch_duration_minutes' => 45, 'lunch_after_hours' => 5],
            ['name' => 'Maple Heights Condos', 'address' => '200 Maple Ave, Bridgeport, CT', 'status' => 'ON_HOLD', 'start_date' => '2026-03-01', 'lunch_duration_minutes' => 30, 'lunch_after_hours' => 6],
            ['name' => 'Harbor View Plaza', 'address' => '15 Harbor Dr, Norwalk, CT', 'status' => 'COMPLETED', 'start_date' => '2025-06-01', 'end_date' => '2026-01-30', 'lunch_duration_minutes' => 30, 'lunch_after_hours' => 6],
        ];

        $jobs = [];
        foreach ($jobData as $jd) {
            $jobs[] = Job::create(array_merge($jd, ['tenant_id' => $tenant->id]));
        }

        // Geofences
        $geofenceData = [
            ['name' => 'Riverside - Main Entrance', 'job_id' => $jobs[0]->id, 'latitude' => 41.7637, 'longitude' => -72.6851, 'radius_meters' => 150],
            ['name' => 'Riverside - Parking Lot', 'job_id' => $jobs[0]->id, 'latitude' => 41.7640, 'longitude' => -72.6845, 'radius_meters' => 100],
            ['name' => 'Oak Street - Site', 'job_id' => $jobs[1]->id, 'latitude' => 41.3083, 'longitude' => -72.9279, 'radius_meters' => 200],
            ['name' => 'Downtown Office - Lobby', 'job_id' => $jobs[2]->id, 'latitude' => 41.0534, 'longitude' => -73.5387, 'radius_meters' => 100],
            ['name' => 'Downtown Office - Roof Access', 'job_id' => $jobs[2]->id, 'latitude' => 41.0536, 'longitude' => -73.5385, 'radius_meters' => 80],
            ['name' => 'Maple Heights - Gate', 'job_id' => $jobs[3]->id, 'latitude' => 41.1865, 'longitude' => -73.1952, 'radius_meters' => 120],
            ['name' => 'Harbor View - Main', 'job_id' => $jobs[4]->id, 'latitude' => 41.1177, 'longitude' => -73.4082, 'radius_meters' => 180, 'is_active' => false],
        ];

        foreach ($geofenceData as $gd) {
            Geofence::create(array_merge($gd, [
                'tenant_id' => $tenant->id,
                'is_active' => $gd['is_active'] ?? true,
            ]));
        }

        // Time Entries — last 2 weeks of realistic data
        $now = Carbon::now();
        $twoWeeksAgo = $now->copy()->subDays(14)->startOfDay();

        for ($day = $twoWeeksAgo->copy(); $day->lt($now->copy()->startOfDay()); $day->addDay()) {
            if ($day->isWeekend()) continue;

            // 8-10 employees work each day
            $workingEmployees = collect($employees)->random(rand(8, min(10, count($employees))));

            foreach ($workingEmployees as $emp) {
                $clockIn = $day->copy()->addHours(rand(6, 8))->addMinutes(rand(0, 45));
                $clockOut = $clockIn->copy()->addHours(rand(7, 10))->addMinutes(rand(0, 30));
                $totalHours = round($clockIn->diffInMinutes($clockOut) / 60, 2);

                // Older entries are approved, recent ones vary
                $daysAgo = $now->diffInDays($day);
                if ($daysAgo > 7) {
                    $status = 'APPROVED';
                } elseif ($daysAgo > 3) {
                    $status = collect(['SUBMITTED', 'APPROVED'])->random();
                } else {
                    $status = collect(['ACTIVE', 'SUBMITTED'])->random();
                }

                $activeJobs = collect($jobs)->where('status', 'ACTIVE');
                $job = $activeJobs->random();

                TimeEntry::create([
                    'tenant_id' => $tenant->id,
                    'employee_id' => $emp->id,
                    'job_id' => $job->id,
                    'team_id' => $emp->current_team_id,
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'clock_in_lat' => 41.7637 + (rand(-100, 100) / 100000),
                    'clock_in_lng' => -72.6851 + (rand(-100, 100) / 100000),
                    'clock_out_lat' => 41.7637 + (rand(-100, 100) / 100000),
                    'clock_out_lng' => -72.6851 + (rand(-100, 100) / 100000),
                    'clock_method' => 'GEOFENCE',
                    'total_hours' => $totalHours,
                    'overtime_hours' => max(0, $totalHours - 8),
                    'status' => $status,
                    'verification_status' => 'NOT_REQUIRED',
                ]);
            }
        }

        // A few employees clocked in today (still active)
        $todayWorkers = collect($employees)->random(4);
        foreach ($todayWorkers as $emp) {
            $clockIn = $now->copy()->startOfDay()->addHours(rand(6, 8))->addMinutes(rand(0, 30));
            $activeJobs = collect($jobs)->where('status', 'ACTIVE');

            TimeEntry::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $emp->id,
                'job_id' => $activeJobs->random()->id,
                'team_id' => $emp->current_team_id,
                'clock_in' => $clockIn,
                'clock_out' => null,
                'clock_in_lat' => 41.7637 + (rand(-100, 100) / 100000),
                'clock_in_lng' => -72.6851 + (rand(-100, 100) / 100000),
                'clock_method' => 'GEOFENCE',
                'total_hours' => null,
                'status' => 'ACTIVE',
                'verification_status' => 'NOT_REQUIRED',
            ]);
        }

        // Transfers
        Transfer::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employees[4]->id, // Lisa
            'from_team_id' => $teams[0]->id,
            'to_team_id' => $teams[3]->id,
            'reason_category' => 'OPERATIONAL',
            'reason_code' => 'PROJECT_NEED',
            'transfer_type' => 'PERMANENT',
            'effective_date' => $now->copy()->subDays(20),
            'initiated_by' => $admin->id,
            'status' => 'COMPLETED',
        ]);

        Transfer::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employees[7]->id, // Kevin
            'from_team_id' => $teams[1]->id,
            'to_team_id' => $teams[2]->id,
            'reason_category' => 'EMPLOYEE_REQUEST',
            'reason_code' => 'PERSONAL_REQUEST',
            'transfer_type' => 'TEMPORARY',
            'effective_date' => $now->copy()->addDays(3),
            'expected_return_date' => $now->copy()->addDays(30),
            'initiated_by' => $admin->id,
            'status' => 'PENDING',
        ]);

        // PTO Balances
        foreach ($employees as $emp) {
            PtoBalance::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $emp->id,
                'type' => 'VACATION',
                'accrued_hours' => 80,
                'used_hours' => rand(0, 40),
                'balance_hours' => 80 - rand(0, 40),
                'year' => $now->year,
            ]);
            PtoBalance::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $emp->id,
                'type' => 'SICK',
                'accrued_hours' => 40,
                'used_hours' => rand(0, 16),
                'balance_hours' => 40 - rand(0, 16),
                'year' => $now->year,
            ]);
        }

        // PTO Requests
        PtoRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employees[2]->id, // Maria
            'type' => 'VACATION',
            'start_date' => $now->copy()->addDays(14),
            'end_date' => $now->copy()->addDays(18),
            'hours' => 40,
            'status' => 'PENDING',
        ]);

        PtoRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employees[8]->id, // Patricia
            'type' => 'SICK',
            'start_date' => $now->copy()->subDays(5),
            'end_date' => $now->copy()->subDays(4),
            'hours' => 16,
            'status' => 'APPROVED',
            'reviewed_by' => $admin->id,
            'reviewed_at' => $now->copy()->subDays(5),
        ]);

        PtoRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employees[6]->id, // Ana
            'type' => 'PERSONAL',
            'start_date' => $now->copy()->addDays(7),
            'end_date' => $now->copy()->addDays(7),
            'hours' => 8,
            'status' => 'PENDING',
        ]);
    }
}
