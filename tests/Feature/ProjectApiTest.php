<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_projects()
    {
        Project::create([
            'name' => 'Test Project',
            'client' => 'Test Client',
            'deadline' => now(),
            'status' => 'active',
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/projects');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Test Project');
    }

    public function test_can_create_project()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/projects', [
                'name' => 'New Project',
                'client' => 'New Client',
                'deadline' => now()->format('Y-m-d'),
                'status' => 'planning',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'New Project');

        $this->assertDatabaseHas('projects', ['name' => 'New Project']);
    }
}
