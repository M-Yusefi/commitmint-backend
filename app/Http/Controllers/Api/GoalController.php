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

        $goals = Goal::where('user_id', $user_id)->get();

        $goalsWithStats = $goals->map(function($goal) {
            $stats = $this->calculateGoalStats($goal);
            
            // Calculate goal end date
            $goalCreatedDate = Carbon::parse($goal->created_at);
            $goalEndDate = $goalCreatedDate->addWeeks($goal->duration_weeks);
            $today = Carbon::today();
            
            // Update status to 'completed' if duration has passed or the total required check-ins were reached
            $currentStatus = $goal->status;
            if (($today->gte($goalEndDate) || $stats['progress']['all_checkins'] >= $stats['progress']['total']) && $goal->status !== 'completed') {
                $goal->status = 'completed';
                $goal->save();
                $currentStatus = 'completed';
            }
            
            // Calculate weekly remaining and next checkin only for active goals
            $weeklyRemaining = null;
            $nextCheckinAt = null;
            
            if ($currentStatus === 'active') {
                $today = Carbon::today();
                $goalStartDate = Carbon::parse($goal->created_at)->startOfDay();
                
                // Welke week van het goal zitten we in?
                $weekNumber = intdiv($today->diffInDays($goalStartDate), 7);
                $currentWeekStart = $goalStartDate->copy()->addWeeks($weekNumber);
                
                // Hoeveel dagen zijn voorbij in deze week
                $daysInCurrentWeek = $today->diffInDays($currentWeekStart) + 1;
                
                // Checkins deze week
                $completedCheckins = Checkin::where('goal_id', $goal->id)
                                            ->whereBetween('date', [$currentWeekStart->toDateString(), $today->toDateString()])
                                            ->count();
                
                // Gemiste checkins
                $missedCheckinsThisWeek = max(0, $daysInCurrentWeek - $completedCheckins);
                
                // Weekly remaining
                $weeklyRemaining = max(0, $goal->times_per_week - $completedCheckins - $missedCheckinsThisWeek);
                
                // Calculate next checkin
                $dailyCheckinExists = Checkin::where('goal_id', $goal->id)
                                            ->whereDate('date', $today)
                                            ->exists();
                $nextCheckinAt = $dailyCheckinExists ? $today->addDay()->format('d/m/Y') : $today->format('d/m/Y');
            }
            
            $allCheckins = Checkin::where('goal_id', $goal->id)->get();
            $totalCheckins = $allCheckins->count();
            $avgProductivityValue = $totalCheckins > 0 ? round($allCheckins->avg('productivity') / 10, 2) : 0;
            $avgFocusValue = $totalCheckins > 0 ? round($allCheckins->avg('focus') / 10, 2) : 0;
            $avgHonestyValue = $totalCheckins > 0 ? round($allCheckins->avg('honesty') / 10, 2) : 0;

            $goalData = [
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
                'failed_checkins'  => $stats['progress']['failed_checkins'],
                'missed_checkins'  => $stats['progress']['missed_checkins'],
                'progress_value' => $stats['progress']['progress_value'],
                
                // Stake, Next chcek-in en 
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
            
            return $goalData;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Goals retrieved successfully.',
            'goals'   => $goalsWithStats,
        ]);
    }
}
