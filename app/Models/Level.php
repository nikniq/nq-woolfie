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

    public const TILE_HIDE = 6;

    public const TILE_PORTAL = 7;

    /** Timed spike plate: cycles between retracted and raised. */
    public const TILE_SPIKES = 8;

    /** Tripwire: sprinting across it sounds the alarm, walking is safe. */
    public const TILE_TRIPWIRE = 9;

    /** Trapdoor: drops the player into the room below on the castle map. */
    public const TILE_TRAPDOOR = 10;

    /** Gas vent: periodic cloud that reverses the controls for a few seconds. */
    public const TILE_GAS = 11;

    /** Crumbling floor: becomes a pit after the player steps off it. */
    public const TILE_CRUMBLE = 12;

    /** Pit left behind by crumbled floor (runtime only, never seeded). */
    public const TILE_PIT = 13;

    /** The war plans: the campaign's main objective. */
    public const TILE_PLANS = 14;

    /** Pistol pickup. */
    public const TILE_GUN = 15;

    /** Ammunition box. */
    public const TILE_AMMO = 16;

    /** Locked cell with a prisoner to free. */
    public const TILE_PRISONER = 17;

    protected $fillable = ['number', 'name', 'map', 'player_start', 'guards', 'portals', 'traps', 'objectives', 'castle_x', 'castle_y'];

    protected function casts(): array
    {
        return [
            'map' => 'array',
            'player_start' => 'array',
            'guards' => 'array',
            'portals' => 'array',
            'traps' => 'array',
            'objectives' => 'array',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(GameRun::class);
    }
}
