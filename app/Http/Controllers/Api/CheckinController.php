<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Goal;
use App\Models\Checkin;
use Carbon\Carbon;

class CheckinController extends Controller
{
    public function store(Request $request, Goal $goal)
    {
        $alreadyCheckedIn = Checkin::where('goal_id', $goal->id)
                                   ->whereDate('date', Carbon::today())
                                   ->exists();

        if ($alreadyCheckedIn) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Already checked in today.',
                'goal'    => $goal,
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

        return response()->json([
            'status'  => 'success',
            'message' => 'Check-in saved successfully.',
            'checkin' => $checkIn,
            'goal'    => $goal,
        ], 201);
    }
}
