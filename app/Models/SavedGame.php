<?php

namespace App\Models;

use Database\Factories\SavedGameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A registered player's in-progress campaign, mirrored from the browser so
 * it follows them between devices.
 */
class SavedGame extends Model
{
    /** @use HasFactory<SavedGameFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'state'];

    protected function casts(): array
    {
        return ['state' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
