<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DiagnosticQuestionnaire;
use App\Models\Diagnostic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagnosticTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $counselor;
    protected DiagnosticQuestionnaire $questionnaire;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->student = User::factory()->create(['email' => 'student@test.com']);
        $this->student->roles()->create(['role' => 'student', 'approved' => true]);

        $this->counselor = User::factory()->create(['email' => 'counselor@test.com']);
        $this->counselor->roles()->create(['role' => 'counselor', 'approved' => true]);

        // Create test questionnaire
        $this->questionnaire = DiagnosticQuestionnaire::create([
            'title' => 'Test Questionnaire',
            'description' => 'Test Description',
            'questions' => [
                'questions' => [
                    [
                        'id' => 'q1',
                        'category' => 'anxiety',
                        'type' => 'scale',
                        'question' => 'How anxious are you?',
                        'description' => 'Rate from 1-5',
                        'options' => null,
                    ],
                    [
                        'id' => 'q2',
                        'category' => 'depression',
                        'type' => 'scale',
                        'question' => 'How depressed are you?',
                        'description' => 'Rate from 1-5',
                        'options' => null,
                    ],
                ]
            ],
            'status' => 'active',
            'version' => 1,
        ]);
    }

    /** @test */
    public function can_get_active_questionnaire()
    {
        $response = $this->actingAs($this->student)
            ->getJson('/api/diagnostics/questionnaire');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'questions',
                'status',
                'version',
            ]);
    }

    /** @test */
    public function can_submit_diagnostic_assessment()
    {
        $responses = [
            'q1' => 4,
            'q2' => 3,
        ];

        $response = $this->actingAs($this->student)
            ->postJson('/api/diagnostics/analyze', [
                'responses' => $responses,
                'questionnaire_id' => $this->questionnaire->id,
                'is_anonymous' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'diagnostic' => [
                    'id',
                    'total_score',
                    'risk_level',
                    'category_scores',
                    'ai_recommendations',
                    'created_at',
                ],
            ]);

        // Verify data was saved
        $this->assertDatabaseHas('diagnostics', [
            'student_id' => $this->student->id,
            'is_anonymous' => false,
        ]);
    }

    /** @test */
    public function can_submit_anonymous_assessment()
    {
        $responses = ['q1' => 2, 'q2' => 2];

        $response = $this->actingAs($this->student)
            ->postJson('/api/diagnostics/analyze', [
                'responses' => $responses,
                'questionnaire_id' => $this->questionnaire->id,
                'is_anonymous' => true,
            ]);

        $response->assertStatus(201);

        $diagnostic = Diagnostic::where('student_id', $this->student->id)->first();
        $this->assertTrue($diagnostic->is_anonymous);
        $this->assertNotNull($diagnostic->anonymous_id);
        $this->assertStringStartsWith('ANON-', $diagnostic->anonymous_id);
    }

    /** @test */
    public function scoring_algorithm_calculates_correctly()
    {
        $responses = ['q1' => 5, 'q2' => 5]; // High scores

        $response = $this->actingAs($this->student)
            ->postJson('/api/diagnostics/analyze', [
                'responses' => $responses,
                'questionnaire_id' => $this->questionnaire->id,
                'is_anonymous' => false,
            ]);

        $response->assertStatus(201);
        $data = $response->json('diagnostic');

        // High scores should result in high risk
        $this->assertGreaterThan(50, $data['total_score']);
        $this->assertContains($data['risk_level'], ['high', 'critical', 'medium']);
    }

    /** @test */
    public function low_scores_result_in_low_risk()
    {
        $responses = ['q1' => 1, 'q2' => 1]; // Low scores

        $response = $this->actingAs($this->student)
            ->postJson('/api/diagnostics/analyze', [
                'responses' => $responses,
                'questionnaire_id' => $this->questionnaire->id,
                'is_anonymous' => false,
            ]);

        $response->assertStatus(201);
        $data = $response->json('diagnostic');

        // Low scores should result in low risk
        $this->assertLessThan(35, $data['total_score']);
        $this->assertEquals('low', $data['risk_level']);
    }

    /** @test */
    public function can_get_diagnostic_history()
    {
        // Create multiple diagnostics
        for ($i = 0; $i < 3; $i++) {
            Diagnostic::create([
                'student_id' => $this->student->id,
                'responses' => ['q1' => 2, 'q2' => 2],
                'total_score' => 30 + ($i * 10),
                'risk_level' => 'low',
                'category_scores' => ['anxiety' => 30, 'depression' => 30],
                'ai_recommendations' => ['primary' => 'Test', 'actions' => []],
                'is_anonymous' => false,
            ]);
        }

        $response = $this->actingAs($this->student)
            ->getJson('/api/diagnostics/history');

        $response->assertStatus(200)
            ->assertJsonIsArray()
            ->assertJsonCount(3);
    }

    /** @test */
    public function can_get_latest_diagnostic()
    {
        Diagnostic::create([
            'student_id' => $this->student->id,
            'responses' => ['q1' => 2, 'q2' => 2],
            'total_score' => 30,
            'risk_level' => 'low',
            'category_scores' => ['anxiety' => 30, 'depression' => 30],
            'ai_recommendations' => ['primary' => 'Test', 'actions' => []],
            'is_anonymous' => false,
        ]);

        $response = $this->actingAs($this->student)
            ->getJson('/api/diagnostics/latest');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'total_score',
                'risk_level',
                'category_scores',
            ]);
    }

    /** @test */
    public function can_get_diagnostic_trends()
    {
        // Create diagnostics over time
        for ($i = 0; $i < 5; $i++) {
            Diagnostic::create([
                'student_id' => $this->student->id,
                'responses' => ['q1' => 2 + $i, 'q2' => 2 + $i],
                'total_score' => 30 + ($i * 5),
                'risk_level' => 'low',
                'category_scores' => ['anxiety' => 30, 'depression' => 30],
                'ai_recommendations' => ['primary' => 'Test', 'actions' => []],
                'is_anonymous' => false,
                'created_at' => now()->subDays(5 - $i),
            ]);
        }

        $response = $this->actingAs($this->student)
            ->getJson('/api/diagnostics/trends?days=30');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'days',
                'trends',
                'latest',
            ]);
    }

    /** @test */
    public function counselor_can_view_dashboard()
    {
        // Create high-risk diagnostic
        Diagnostic::create([
            'student_id' => $this->student->id,
            'responses' => ['q1' => 5, 'q2' => 5],
            'total_score' => 85,
            'risk_level' => 'critical',
            'category_scores' => ['anxiety' => 85, 'depression' => 85],
            'ai_recommendations' => ['primary' => 'Urgent', 'actions' => []],
            'is_anonymous' => false,
        ]);

        $response = $this->actingAs($this->counselor)
            ->getJson('/api/diagnostics/counselor-dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'high_risk',
                'recent',
                'risk_distribution',
            ]);
    }

    /** @test */
    public function student_cannot_view_counselor_dashboard()
    {
        $response = $this->actingAs($this->student)
            ->getJson('/api/diagnostics/counselor-dashboard');

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_submit_assessment()
    {
        $response = $this->postJson('/api/diagnostics/analyze', [
            'responses' => ['q1' => 2],
            'questionnaire_id' => $this->questionnaire->id,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function recommendations_are_generated_for_high_risk()
    {
        $responses = ['q1' => 5, 'q2' => 5];

        $response = $this->actingAs($this->student)
            ->postJson('/api/diagnostics/analyze', [
                'responses' => $responses,
                'questionnaire_id' => $this->questionnaire->id,
                'is_anonymous' => false,
            ]);

        $response->assertStatus(201);
        $recommendations = $response->json('diagnostic.ai_recommendations');

        $this->assertNotNull($recommendations['primary']);
        $this->assertIsArray($recommendations['actions']);
        $this->assertGreaterThan(0, count($recommendations['actions']));
    }

    /** @test */
    public function category_scores_are_calculated()
    {
        $responses = ['q1' => 4, 'q2' => 2];

        $response = $this->actingAs($this->student)
            ->postJson('/api/diagnostics/analyze', [
                'responses' => $responses,
                'questionnaire_id' => $this->questionnaire->id,
                'is_anonymous' => false,
            ]);

        $response->assertStatus(201);
        $categoryScores = $response->json('diagnostic.category_scores');

        $this->assertIsArray($categoryScores);
        $this->assertArrayHasKey('anxiety', $categoryScores);
        $this->assertArrayHasKey('depression', $categoryScores);
    }

    /** @test */
    public function questionnaire_endpoint_bootstraps_default_when_missing(): void
    {
        DiagnosticQuestionnaire::query()->delete();

        $response = $this->actingAs($this->student)->getJson('/api/diagnostics/questionnaire');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'questions',
                'status',
            ]);
        $this->assertGreaterThan(0, DiagnosticQuestionnaire::query()->count());
    }
}
