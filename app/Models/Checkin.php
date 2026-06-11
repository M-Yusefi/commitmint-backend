<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Goal;

class Checkin extends Model
{
    use HasFactory;

    protected $fillable = [
        'goal_id',
        'date',
        'done',
        'productivity',
        'focus',
        'honesty',
        'reason',
    ];
    
    public function goal() 
    {
        return $this->belongsTo(Goal::class);
    }
}
