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
        $completedCheckins = $allCheckins->count();
        
        $progressValue = $totalCheckins > 0 ? round($completedCheckins / $totalCheckins, 6) : 0;
        
        $stakePerCheckin = $totalCheckins > 0 ? $goal->stake_amount / $totalCheckins : 0;
        $completedCount = $allCheckins->where('done', true)->count();
        $failedFromCheckins = $allCheckins->where('done', false)->count();
        $earned = $completedCount * $stakePerCheckin;
        
        // Calculate missed days (days without any checkin)
        $goalCreatedDate = Carbon::parse($goal->created_at)->startOfDay();
        $today = Carbon::today();
        $missedDays = 0;
        
        for ($date = $goalCreatedDate; $date->lte($today); $date->addDay()) {
            $checkinExists = $allCheckins->where('date', $date->toDateString())->count() > 0;
            if (!$checkinExists) {
                $missedDays++;
            }
        }
        
        // If goal is completed, add missed days to failed count
        $missedCount = $failedFromCheckins;
        $lostFromMissedDays = $missedDays * $stakePerCheckin;
        
        if ($goal->status === 'completed') {
            $missedCount = $failedFromCheckins + $missedDays;
        }
        
        $lost = $missedCount * $stakePerCheckin;
        
        return [
            'progress' => [
                'completed' => $completedCheckins,
                'total' => $totalCheckins,
                'progress_value' => $progressValue,
            ],
            'stake' => [
                'total_stake' => $goal->stake_amount,
                'stake_per_checkin' => round($stakePerCheckin, 2),
                'completed_count' => $completedCount,
                'missed_count' => intval($missedCount),
                'earned' => round($earned, 2),
                'lost' => round($lost, 2),
                'lost_from_missed_days' => round($lostFromMissedDays, 2),
            ],
            'missed_days' => $missedDays,
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
            'progress'          => $stats['progress'],
            'stake'             => $stats['stake'],
            'missed_days'       => $stats['missed_days'],
            'goal_status'       => $goalStatus,
            'goal_completed'    => $goalCompleted,
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
            'goal'     => $goal,
            'stats'    => [
                'total_checkins'    => $totalCheckins,
                'completed_goals'   => $completedGoals,
                'completion_rate'   => $completionRate . '%',
                'avg_productivity'  => $avgProductivity,
                'avg_focus'         => $avgFocus,
                'avg_honesty'       => $avgHonesty,
                'goal_progress'     => round($goalProgress, 2) . '%',
                'missed_days'       => $stats['missed_days'],
            ],
            'progress'  => $stats['progress'],
            'stake'     => $stats['stake'],
            'end_date'  => $goalEndDate->format('d/m/Y'),
        ]);
    }
}
