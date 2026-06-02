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
        $today = Carbon::now()->dayOfWeek;

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
            'date'    => Carbon::today()->toDateString(),
            'done'    => $validated['done'],
            'prod'    => $validated['prod'],
            'focus'   => $validated['focus'],
            'opinion' => $validated['opinion'] ?? null,
            'improve' => $validated['improve'] ?? null,
            'reason'  => $validated['reason'] ?? null,
            'honest'  => $validated['honest'],
            'promise' => $validated['promise'] ?? null,
            'mood'    => $validated['mood'] ?? null,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Check-in saved successfully.',
            'checkin' => $checkIn,
            'goal'    => $goal,
        ], 201);
    }
}
