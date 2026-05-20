<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Goal;
use Carbon\Carbon;

class GoalController extends Controller
{
    public function store(Request $request) 
    {
        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'category'        => 'required|string',
            'selected_days'   => 'required|array',
            'duration_weeks'  => 'required|integer|min:1',
            'start_date'      => 'required|date',
            'stake_amount'    => 'required|integer|min:1',
            'agreed_to_rules' => 'required|boolean'
        ]);


        $goal = Goal::create([
            'user_id'         => auth()->id(),
            'title'           => $validated['title'],
            'category'        => $validated['category'],
            'selected_days'   => $validated['selected_days'],
            'duration_weeks'  => $validated['duration_weeks'],
            'start_date'      => $validated['start_date'],
            'stake_amount'    => $validated['stake_amount'],
            'agreed_to_rules' => $validated['agreed_to_rules'],
            'status'          => Carbon::parse($validated['start_date'])->isToday() ? 'active' : 'pending'
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Goal created successfully.',
            'goal'    => $goal,
        ], 201);
    }
    

    public function index()
    {
        $user_id = auth()->id();

        $goals = Goal::where('user_id', $user_id)->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Goals retrieved successfully.',
            'goals'   => $goals,
        ]);
    }
}
