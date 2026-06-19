<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Project::create([
            'name' => 'Campus Wellness Portal',
            'client' => 'Africa University',
            'deadline' => now()->addMonths(3),
            'status' => 'In Progress',
        ]);

        Project::create([
            'name' => 'Mobile Mental Health App',
            'client' => 'Ministry of Health',
            'deadline' => now()->addMonths(6),
            'status' => 'Planning',
        ]);

        Project::create([
            'name' => 'Counselor Management System',
            'client' => 'Internal',
            'deadline' => now()->subDays(5),
            'status' => 'Completed',
        ]);
    }
}
