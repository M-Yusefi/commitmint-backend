<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Goal;
use App\Models\Checkin;
use Carbon\Carbon;

class CheckinController extends Controller
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
        
        $stakePerCheckin = $totalCheckins > 0 ? $goal->stake_amount / $totalCheckins : 0;
        $successCheckins = $allCheckins->where('done', true)->count();
        $failedCheckins = $allCheckins->where('done', false)->count();
        $expectedCheckins = $elapsedWeeks * $goal->times_per_week;
        $missedCheckins = max(0, min($totalCheckins, $expectedCheckins - $actualCheckins));
        
        $earned = $successCheckins * $stakePerCheckin;
        $lostFromFailed = $failedCheckins * $stakePerCheckin;
        $lostFromMissed = $missedCheckins * $stakePerCheckin;
        $totalLost = $lostFromFailed + $lostFromMissed;
        
        // Evaluated check-ins = completed + failed + missed (for progress bar calculation)
        $evaluatedCheckins = $successCheckins + $failedCheckins + $missedCheckins;
        
        // Calculate progress value based on evaluated check-ins (for progress bar)
        $progressValue = 0.0;
        if ($totalCheckins > 0) {
            $progressValue = $evaluatedCheckins / $totalCheckins;
            $progressValue = min(1.0, max(0.0, $progressValue));
        }
        
        // Calculate success percentage based on evaluated check-ins (0-100)
        $successPercentage = 0.0;
        if ($evaluatedCheckins > 0) {
            $successPercentage = ($successCheckins / $evaluatedCheckins) * 100;
            $successPercentage = min(100.0, max(0.0, $successPercentage));
        }

        return [
            'progress' => [
                'total' => $totalCheckins,
                'all_checkins' => $evaluatedCheckins,
                'success_checkins' => $successCheckins,
                'failed_checkins' => $failedCheckins,
                'missed_checkins' => $missedCheckins,
                'progress_value' => round($progressValue, 6),
            ],
            'stake' => [
                'total_stake' => $goal->stake_amount,
                'stake_per_checkin' => round($stakePerCheckin, 2),
                'earned' => round($earned, 2),
                'lost_from_failed' => round($lostFromFailed, 2),
                'lost_from_missed' => round($lostFromMissed, 2),
                'lost' => round($totalLost, 2),
                'success_percentage' => round($successPercentage),
            ],
        ];
    }

    public function store(Request $request, Goal $goal)
    {
        if ($goal->user_id !== auth()->id()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized goal.',
            ], 403);
        }

        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $dailyCheckinExists = Checkin::where('goal_id', $goal->id)
                                     ->whereDate('date', $today)
                                     ->exists();

        if ($dailyCheckinExists) {
            return response()->json([
                'status'           => 'error',
                'message'          => 'Already checked in today.',
                'next_checkin_at'  => $tomorrow->format('d/m/Y'),
            ], 409);
        }

        $weeklyCheckins = Checkin::where('goal_id', $goal->id)
                                 ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                                 ->count();

        if ($weeklyCheckins >= $goal->times_per_week) {
            return response()->json([
                'status'           => 'error',
                'message'          => 'Weekly checkin limit reached.',
                'next_checkin_at'  => $weekStart->addWeek()->format('d/m/Y'),
            ], 409);
        }

        $validated = $request->validate([
            'done'         => 'required|boolean',
            'productivity' => 'required|integer|min:1|max:10',
            'focus'        => 'required|integer|min:1|max:10',
            'honesty'      => 'required|integer|min:1|max:10',
            'reason'       => 'nullable|string|max:255',
        ]);

        $checkIn = Checkin::create([
            'goal_id'      => $goal->id,
            'date'         => Carbon::today()->toDateString(),
            'done'         => $validated['done'],
            'productivity' => $validated['productivity'],
            'focus'        => $validated['focus'],
            'honesty'      => $validated['honesty'],
            'reason'       => $validated['reason'] ?? null,
        ]);

        // Check if goal should be marked as completed
        $goalCreatedDate = Carbon::parse($goal->created_at);
        $goalEndDate = $goalCreatedDate->addWeeks($goal->duration_weeks);
        $today = Carbon::today();
        
        $goalStatus = $goal->status;
        $goalCompleted = false;
        
        if ($today->gte($goalEndDate) && $goal->status !== 'completed') {
            $goal->status = 'completed';
            $goal->save();
            $goalStatus = 'completed';
            $goalCompleted = true;
        }

        $stats = $this->calculateGoalStats($goal);

        return response()->json([
            'status'            => 'success',
            'message'           => $goalCompleted ? 'Check-in saved successfully. Goal completed!' : 'Check-in saved successfully.',
            'checkin'           => $checkIn,
            'next_checkin_at'   => $tomorrow->format('d/m/Y'),
            'weekly_remaining'  => max(0, $goal->times_per_week - ($weeklyCheckins + 1)),
            'all_checkins' => $stats['progress']['all_checkins'],
            'total' => $stats['progress']['total'],
            'success_checkins' => $stats['progress']['success_checkins'],
            'failed_checkins' => $stats['progress']['failed_checkins'],
            'missed_checkins' => $stats['progress']['missed_checkins'],
            'progress_value' => $stats['progress']['progress_value'],
            'stake_per_checkin' => $stats['stake']['stake_per_checkin'],
            'earned' => $stats['stake']['earned'],
            'lost' => $stats['stake']['lost'],
            'success_percentage' => $stats['stake']['success_percentage'],
            'goal_status'       => $goalStatus,
            'goal_completed'    => $goalCompleted,
            'progress'          => $stats['progress'],
            'stake'             => $stats['stake'],
        ], 201);    
    }

    public function index(Request $request, Goal $goal)
    {
        if ($goal->user_id !== auth()->id()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized goal.',
            ], 403);
        }

        $checkins = Checkin::where('goal_id', $goal->id)
                          ->orderBy('date', 'desc')
                          ->get();

        return response()->json([
            'status'   => 'success',
            'message'  => 'Check-ins retrieved successfully.',
            'checkins' => $checkins,
            'goal'     => $goal,
        ]);
    }

    public function show(Goal $goal, Checkin $checkin)
    {
        if ($goal->user_id !== auth()->id()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized goal.',
            ], 403);
        }

        if ($checkin->goal_id !== $goal->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Checkin not found for this goal.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'checkin' => $checkin,
            'goal'    => $goal,
        ]);
    }

    public function stats(Goal $goal)
    {
        if ($goal->user_id !== auth()->id()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized goal.',
            ], 403);
        }

        // Check if goal should be marked as completed
        $goalCreatedDate = Carbon::parse($goal->created_at);
        $goalEndDate = $goalCreatedDate->addWeeks($goal->duration_weeks);
        $today = Carbon::today();
        
        if ($today->gte($goalEndDate) && $goal->status !== 'completed') {
            $goal->status = 'completed';
            $goal->save();
        }

        $allCheckins = Checkin::where('goal_id', $goal->id)->get();
        $totalCheckins = count($allCheckins);

        $avgProductivity = $totalCheckins > 0 
            ? round($allCheckins->avg('productivity'), 2) 
            : 0;

        $avgFocus = $totalCheckins > 0 
            ? round($allCheckins->avg('focus'), 2) 
            : 0;

        $avgHonesty = $totalCheckins > 0 
            ? round($allCheckins->avg('honesty'), 2) 
            : 0;

        $completedGoals = $allCheckins->where('done', true)->count();
        $completionRate = $totalCheckins > 0 
            ? round(($completedGoals / $totalCheckins) * 100, 2) 
            : 0;

        $daysCompleted = $allCheckins->count();
        $goalProgress = ($daysCompleted / ($goal->times_per_week * $goal->duration_weeks)) * 100;

        $stats = $this->calculateGoalStats($goal);

        return response()->json([
            'status'   => 'success',
            'message'  => 'Goal stats retrieved successfully.',
            'goal'     => $goal,
            'end_date' => $goalEndDate->format('d/m/Y'),
            'all_checkins' => $stats['progress']['all_checkins'],
            'total' => $stats['progress']['total'],
            'success_checkins' => $stats['progress']['success_checkins'],
            'failed_checkins' => $stats['progress']['failed_checkins'],
            'missed_checkins' => $stats['progress']['missed_checkins'],
            'progress_value' => $stats['progress']['progress_value'],
            'stake_per_checkin' => $stats['stake']['stake_per_checkin'],
            'earned' => $stats['stake']['earned'],
            'lost' => $stats['stake']['lost'],
            'success_percentage' => $stats['stake']['success_percentage'],
            
            'stats' => [
                'total_checkins'    => $totalCheckins,
                'completed_goals'   => $completedGoals,
                'completion_rate'   => $completionRate . '%',
                'avg_productivity'  => $avgProductivity,
                'avg_focus'         => $avgFocus,
                'avg_honesty'       => $avgHonesty,
                'goal_progress'     => round($goalProgress, 2) . '%',
            ],
            
            'progress'  => $stats['progress'],
            'stake'     => $stats['stake'],
        ]);
    }
}
