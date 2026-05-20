<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Goal;
use App\Models\Checkin;
use Carbon\Carbon;

class CheckinController extends Controller
{
    public function checkin(Request $request, Goal $goal)
    {
        // Prevent duplicate check-in for today 
            $alreadyCheckedIn = Checkin::where('user_id', $user->id)
                                       ->where('goal_id', $goal->id)
                                       ->whereDate('date', Carbon::now()) 
                                       ->exists();

            if ($alreadyCheckedIn) {
                return response()->json([
                    'message' => 'Already checked in today',
                    'goal' => $goal
                ]);
            }
        // end check

        // Ensure today is a valid check-in day 
            $today = Carbon::parse()->dayOfWeek();
            $selected_days = $goal->selected_days;
            if (!in_array($today, $selected_days)) {
                return response()->json([
                    'message' => 'Not allowed',
                    'goal' => $goal
                ]);
            }
        // end check


        $validated = $request->validate([
            'done'     => 'required|boolean',
            'prod'     => 'required|integer|min:1|max:5',
            'focus'    => 'required|integer|min:1|max:5',
            'opinion'  => 'nullable|string|max:255',
            'improve'  => 'nullable|string|max:255',
            'reason'   => 'required_if:done,false|string|max:255',
            'honest'   => 'required|integer|min:1|max:5',
            'promise'  => 'nullable|string|max:255',
            'mood'     => 'nullable|string|max:50',            
        ]);

        $checkIn = Checkin::create([
            'goal_id' => $goal->id,
            'done'    => $validated['done'],
            'prod'    => $validated['prod'],
            'focus'   => $validated['focus'],
            'opinion' => $validated['opinion'],
            'improve' => $validated['improve'],
            'reason'  => $validated['reason'],
            'honest'  => $validated['honest'],
            'promise' => $validated['promise'],
            'mood'    => $validated['mood']
        ]);
    }
}
