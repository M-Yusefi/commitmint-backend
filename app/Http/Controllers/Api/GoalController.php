<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Goal;
use App\Models\Checkin;
use Carbon\Carbon;

class GoalController extends Controller
{
    private function calculateGoalStats($goal)
    {
        $allCheckins = Checkin::where('goal_id', $goal->id)->get();
        $totalCheckins = $goal->times_per_week * $goal->duration_weeks;
        $actualCheckins = $allCheckins->count();
        
        $progressValue = $totalCheckins > 0 ? round($actualCheckins / $totalCheckins, 6) : 0;
        
        $stakePerCheckin = $totalCheckins > 0 ? $goal->stake_amount / $totalCheckins : 0;
        $completedCount = $allCheckins->where('done', true)->count();
        $failedCount = $allCheckins->where('done', false)->count();
        $missedCheckins = $totalCheckins - $actualCheckins;
        
        $earned = $completedCount * $stakePerCheckin;
        $lostFromFailed = $failedCount * $stakePerCheckin;
        $lostFromMissed = $missedCheckins * $stakePerCheckin;
        $totalLost = $lostFromFailed + $lostFromMissed;
        
        // Calculate success percentage based on completed vs total expected
        $successPercentage = $totalCheckins > 0 ? round(($completedCount / $totalCheckins) * 100, 2) : 0;
        
        return [
            'progress' => [
                'completed' => $actualCheckins,
                'total' => $totalCheckins,
                'progress_value' => $progressValue,
            ],
            'stake' => [
                'total_stake' => $goal->stake_amount,
                'stake_per_checkin' => round($stakePerCheckin, 2),
                'completed_count' => $completedCount,
                'failed_count' => $failedCount,
                'missed_checkins' => $missedCheckins,
                'earned' => round($earned, 2),
                'lost_from_failed' => round($lostFromFailed, 2),
                'lost_from_missed' => round($lostFromMissed, 2),
                'lost' => round($totalLost, 2),
                'success_percentage' => $successPercentage,
            ],
        ];
    }

    public function store(Request $request) 
    {
        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'times_per_week'  => 'required|integer|min:1|max:7',
            'duration_weeks'  => 'required|integer|min:1|max:52',
            'stake_amount'    => 'required|numeric|min:1',
            'agreed_to_rules' => 'required|accepted',
        ]);


        $goal = Goal::create([
            'user_id'         => auth()->id(),
            'title'           => $validated['title'],
            'times_per_week'  => $validated['times_per_week'],
            'duration_weeks'  => $validated['duration_weeks'],
            'stake_amount'    => $validated['stake_amount'],
            'agreed_to_rules' => $validated['agreed_to_rules'],
            'status'          => 'active'
        ]);

        $goalEndDate = Carbon::parse($goal->created_at)->addWeeks($goal->duration_weeks);

        return response()->json([
            'status'  => 'success',
            'message' => 'Goal created successfully.',
            'goal'    => $goal,
            'end_date' => $goalEndDate->format('d/m/Y'),
        ], 201);
    }
    

    public function index()
    {
        $user_id = auth()->id();

        $goals = Goal::where('user_id', $user_id)->get();

        $goalsWithStats = $goals->map(function($goal) {
            $stats = $this->calculateGoalStats($goal);
            
            // Calculate goal end date
            $goalCreatedDate = Carbon::parse($goal->created_at);
            $goalEndDate = $goalCreatedDate->addWeeks($goal->duration_weeks);
            $today = Carbon::today();
            
            // Update status to 'completed' if duration has passed
            $currentStatus = $goal->status;
            if ($today->gte($goalEndDate) && $goal->status !== 'completed') {
                $goal->status = 'completed';
                $goal->save();
                $currentStatus = 'completed';
            }
            
            // Calculate weekly remaining and next checkin only for active goals
            $weeklyRemaining = null;
            $nextCheckinAt = null;
            
            if ($currentStatus === 'active') {
                $weekStart = Carbon::now()->startOfWeek();
                $weekEnd = Carbon::now()->endOfWeek();
                $weeklyCheckinsThisWeek = Checkin::where('goal_id', $goal->id)
                                                ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                                                ->count();
                $weeklyRemaining = max(0, $goal->times_per_week - $weeklyCheckinsThisWeek);
                
                // Calculate next checkin
                $dailyCheckinExists = Checkin::where('goal_id', $goal->id)
                                            ->whereDate('date', Carbon::today())
                                            ->exists();
                $nextCheckinAt = $dailyCheckinExists ? Carbon::tomorrow()->format('d/m/Y') : Carbon::today()->format('d/m/Y');
            }
            
            $goalData = [
                'id' => $goal->id,
                'user_id' => $goal->user_id,
                'title' => $goal->title,
                'times_per_week' => $goal->times_per_week,
                'duration_weeks' => $goal->duration_weeks,
                'stake_amount' => $goal->stake_amount,
                'status' => $currentStatus,
                'created_at' => $goal->created_at,
                'updated_at' => $goal->updated_at,
                'end_date' => $goalEndDate->format('d/m/Y'),
                'progress' => $stats['progress'],
                'stake' => $stats['stake'],
            ];
            
            if ($currentStatus === 'active') {
                $goalData['next_checkin_at'] = $nextCheckinAt;
                $goalData['weekly_remaining'] = $weeklyRemaining;
            }
            
            return $goalData;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Goals retrieved successfully.',
            'goals'   => $goalsWithStats,
        ]);
    }
}
