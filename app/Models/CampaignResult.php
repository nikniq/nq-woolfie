<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignResult extends Model
{
    public const OUTCOME_ESCAPED = 'escaped';

    public const OUTCOME_FAILED = 'failed';

    protected $fillable = [
        'campaign', 'player_name', 'difficulty', 'daily', 'outcome', 'score', 'time_ms', 'kills', 'alarms', 'lives_lost',
        'stones', 'bodies_hidden', 'rooms_cleared', 'rooms_total', 'all_gold', 'all_prisoners', 'disguised_at_end',
    ];

    protected function casts(): array
    {
        return ['all_gold' => 'boolean', 'all_prisoners' => 'boolean', 'disguised_at_end' => 'boolean'];
    }
}
