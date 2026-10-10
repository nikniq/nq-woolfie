<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameRun extends Model
{
    public const OUTCOME_COMPLETED = 'completed';

    public const OUTCOME_CAUGHT = 'caught';

    public const DIFFICULTIES = ['easy', 'normal', 'hard'];

    protected $fillable = ['level_id', 'player_name', 'outcome', 'time_ms', 'score', 'campaign', 'difficulty'];

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
}
