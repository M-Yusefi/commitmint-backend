<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Checkin;


class Goal extends Model
{   
    use HasFactory;

    protected $casts =[
        'times_per_week' => 'integer',
        'duration_weeks' => 'integer',
        'agreed_to_rules' => 'boolean',
        'stake_amount' => 'float',
    ];

    protected $fillable = [
        'user_id',
        'title',
        'times_per_week',
        'duration_weeks',
        'stake_amount',
        'agreed_to_rules',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function checkins()
    {
        return $this->hasMany(Checkin::class);
    }
}
