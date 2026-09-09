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
        $today = Carbon::today();
        $goalCreatedDate = Carbon::parse($goal->created_at)->startOfDay();
        $goalEndDate = $goalCreatedDate->copy()->addWeeks($goal->duration_weeks);
        $elapsedWeeks = $today->gte($goalEndDate)
            ? $goal->duration_weeks
            : min($goal->duration_weeks, intdiv(max(0, $goalCreatedDate->diffInDays($today)), 7));
        
        // Ensure progress_value is between 0.0 and 1.0
        $progressValue = 0.0;
        if ($totalCheckins > 0) {
            $progressValue = $actualCheckins / $totalCheckins;
            $progressValue = min(1.0, max(0.0, $progressValue));
        }
        
        $stakePerCheckin = $totalCheckins > 0 ? $goal->stake_amount / $totalCheckins : 0;
        $completedCount = $allCheckins->where('done', true)->count();
        $failedCount = $allCheckins->where('done', false)->count();
        $expectedCheckins = $elapsedWeeks * $goal->times_per_week;
        $missedCheckins = max(0, min($totalCheckins, $expectedCheckins - $actualCheckins));
        
        $earned = $completedCount * $stakePerCheckin;
        $lostFromFailed = $failedCount * $stakePerCheckin;
        $lostFromMissed = $missedCheckins * $stakePerCheckin;
        $totalLost = $lostFromFailed + $lostFromMissed;
        
        // Calculate success percentage based on evaluated check-ins (0-100)
        $successPercentage = 0.0;
        $evaluatedCheckins = $completedCount + $failedCount + $missedCheckins;
        if ($evaluatedCheckins > 0) {
            $successPercentage = ($completedCount / $evaluatedCheckins) * 100;
            $successPercentage = min(100.0, max(0.0, $successPercentage));
        }
        
        return [
            'progress' => [
                'completed' => $actualCheckins,
                'total' => $totalCheckins,
                'progress_value' => round($progressValue, 6),
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
                'success_percentage' => round($successPercentage),
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
                'duration_weeks' => $goal->duration_weeks,
                'times_per_week' => $goal->times_per_week,
                'stake_amount' => $goal->stake_amount,
                'agreed_to_rules' => (bool) $goal->agreed_to_rules,
                'created_at' => $goal->created_at,
                'updated_at' => $goal->updated_at,
                'status' => $currentStatus,
                'completed' => $stats['progress']['completed'],
                'total' => $stats['progress']['total'],
                'progress_value' => $stats['progress']['progress_value'],
                'stake_per_checkin' => $stats['stake']['stake_per_checkin'],
                'earned' => $stats['stake']['earned'],
                'lost' => $stats['stake']['lost'],
                'weekly_remaining' => $weeklyRemaining ?? 0,
                'next_checkin_at' => $nextCheckinAt ?? null,
                'progress' => $stats['progress'],
                'stake' => $stats['stake'],
            ];
            
            return $goalData;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Goals retrieved successfully.',
            'goals'   => $goalsWithStats,
        ]);
    }
}
