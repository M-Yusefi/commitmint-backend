<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_goal_is_marked_completed_after_reaching_total_checkins(): void
    {
        $user = User::factory()->create();

        $goal = Goal::create([
            'user_id' => $user->id,
            'title' => 'Read 10 pages daily',
            'times_per_week' => 1,
            'duration_weeks' => 1,
            'stake_amount' => 20,
            'agreed_to_rules' => true,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/checkin/store/' . $goal->id, [
                'done' => true,
                'productivity' => 8,
                'focus' => 7,
                'honesty' => 9,
                'reason' => 'Completed the required checkin.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('goal_status', 'completed')
            ->assertJsonPath('goal_completed', true);

        $this->assertDatabaseHas('goals', [
            'id' => $goal->id,
            'status' => 'completed',
        ]);
    }
}
