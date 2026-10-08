<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Level extends Model
{
    public const TILE_FLOOR = 0;

    public const TILE_WALL = 1;

    public const TILE_DOOR = 2;

    public const TILE_KEY = 3;

    public const TILE_EXIT = 4;

    public const TILE_TREASURE = 5;

    protected $fillable = ['number', 'name', 'map', 'player_start', 'guards'];

    protected function casts(): array
    {
        return [
            'map' => 'array',
            'player_start' => 'array',
            'guards' => 'array',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(GameRun::class);
    }
}
