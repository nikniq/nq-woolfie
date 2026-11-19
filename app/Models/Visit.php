<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per new browser session, recording where the visitor came from.
 */
class Visit extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = ['source', 'medium', 'campaign', 'landing'];

    /** Keep a year of visit history. */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subYear());
    }
}
