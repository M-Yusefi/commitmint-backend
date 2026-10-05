<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Goal;
use App\Models\Checkin;
use Carbon\Carbon;

class GoalController extends Controller
{
    private function calculateGoalStats($goal, $allCheckins)
    {
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
            $successPercentage = $successCheckins / $evaluatedCheckins;
            $successPercentage = min(1.0, max(0.0, $successPercentage));
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
                'success_percentage' => round($successPercentage * 100),
                'success_percentage_value' => round($successPercentage, 2),
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

    $goals = Goal::where('user_id', $user_id)->with('checkins')->get();

    $goalsWithStats = $goals->map(function ($goal) {
        $allCheckins = $goal->checkins;
        $stats = $this->calculateGoalStats($goal, $allCheckins);

        $goalCreatedDate = Carbon::parse($goal->created_at);
        $goalEndDate = $goalCreatedDate->addWeeks($goal->duration_weeks);
        $today = Carbon::today();
        $todayStr = $today->toDateString();

        $currentStatus = $goal->status;
        if (($today->gte($goalEndDate) || $stats['progress']['all_checkins'] >= $stats['progress']['total']) && $goal->status !== 'completed') {
            $goal->status = 'completed';
            $goal->save();
            $currentStatus = 'completed';
        }

        $weeklyRemaining = null;
        $nextCheckinAt = null;

        if ($currentStatus === 'active') {
            $goalStartDate = Carbon::parse($goal->created_at)->startOfDay();

            // Welke week van het goal zitten we in?
            $weekNumber = intdiv($today->diffInDays($goalStartDate), 7);
            $currentWeekStart = $goalStartDate->copy()->addWeeks($weekNumber);
            $weekStartStr = $currentWeekStart->toDateString();

            // Hoeveel dagen zijn voorbij in deze week
            $daysInCurrentWeek = $today->diffInDays($currentWeekStart) + 1;

            // Checkins deze week (uit het geheugen, geen query)
            $completedCheckins = $allCheckins->filter(function ($c) use ($weekStartStr, $todayStr) {
                $d = Carbon::parse($c->date)->toDateString();
                return $d >= $weekStartStr && $d <= $todayStr;
            })->count();

            // Gemiste checkins
            $missedCheckinsThisWeek = max(0, $daysInCurrentWeek - $completedCheckins);

            // Weekly remaining
            $weeklyRemaining = max(0, $goal->times_per_week - $completedCheckins - $missedCheckinsThisWeek);

            // Next checkin (uit het geheugen, geen query)
            $dailyCheckinExists = $allCheckins->contains(function ($c) use ($todayStr) {
                return Carbon::parse($c->date)->toDateString() === $todayStr;
            });
            $nextCheckinAt = $dailyCheckinExists
                ? $today->copy()->addDay()->format('d/m/Y')
                : $today->format('d/m/Y');
        }

        $totalCheckins = $allCheckins->count();
       
        $avgProductivityValue = null;
        $avgFocusValue = null;
        $avgHonestyValue = null;

        if ($currentStatus === 'completed') {
            $totalCheckins = $allCheckins->count();

            if ($totalCheckins > 0) {
                $avgProductivityValue = round($allCheckins->avg('productivity') / 10, 2);
                $avgFocusValue = round($allCheckins->avg('focus') / 10, 2);
                $avgHonestyValue = round($allCheckins->avg('honesty') / 10, 2);
            } else {
                $avgProductivityValue = 0;
                $avgFocusValue = 0;
                $avgHonestyValue = 0;
            }
        }
        return [
            // Algemene Goal Data
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

            // Check-in en Progress_Value
            'total' => $stats['progress']['total'],
            'all_checkins' => $stats['progress']['all_checkins'],
            'success_checkins' => $stats['progress']['success_checkins'],
            'failed_checkins' => $stats['progress']['failed_checkins'],
            'missed_checkins' => $stats['progress']['missed_checkins'],
            'progress_value' => $stats['progress']['progress_value'],

            // Stake en Next check-in
            'stake_per_checkin' => $stats['stake']['stake_per_checkin'],
            'earned' => $stats['stake']['earned'],
            'lost' => $stats['stake']['lost'],
            'weekly_remaining' => $weeklyRemaining ?? 0,
            'next_checkin_at' => $nextCheckinAt ?? null,
            'success_percentage' => $stats['stake']['success_percentage'],

            // Gebruiker Stats
            'success_percentage_value' => $stats['stake']['success_percentage_value'],
            'avg_productivity_value' => $avgProductivityValue,
            'avg_focus_value' => $avgFocusValue,
            'avg_honesty_value' => $avgHonestyValue,
        ];
    });

    return response()->json([
        'status'  => 'success',
        'message' => 'Goals retrieved successfully.',
        'goals'   => $goalsWithStats,
    ]);
}}
