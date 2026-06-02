<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Goal;

class GoalController extends Controller
{
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
