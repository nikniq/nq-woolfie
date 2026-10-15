<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    public $timestamps = false;

    protected $fillable = ['player_name', 'key', 'campaign', 'earned_at'];

    protected function casts(): array
    {
        return ['earned_at' => 'datetime'];
    }

    /**
     * Every achievement, its badge and the condition on a finished campaign.
     *
     * @return array<string, array{title: string, icon: string, description: string, test: callable(CampaignResult): bool}>
     */
    public static function catalogue(): array
    {
        $escaped = fn (CampaignResult $r) => $r->outcome === CampaignResult::OUTCOME_ESCAPED;

        return [
            'escapee' => ['title' => 'Escapee', 'icon' => '🏰', 'description' => 'Escape the castle with the war plans.', 'test' => $escaped],
            'ghost' => ['title' => 'Ghost', 'icon' => '👻', 'description' => 'Escape without the alarm ever sounding.', 'test' => fn ($r) => $escaped($r) && $r->alarms === 0],
            'pacifist' => ['title' => 'Pacifist', 'icon' => '🕊', 'description' => 'Escape without firing a single killing shot.', 'test' => fn ($r) => $escaped($r) && $r->kills === 0],
            'marksman' => ['title' => 'Marksman', 'icon' => '🎯', 'description' => 'Shoot five guards in one campaign.', 'test' => fn ($r) => $r->kills >= 5],
            'master_thief' => ['title' => 'Master Thief', 'icon' => '💰', 'description' => 'Escape with every piece of gold in the castle.', 'test' => fn ($r) => $escaped($r) && $r->all_gold],
            'liberator' => ['title' => 'Liberator', 'icon' => '🔓', 'description' => 'Escape having freed every prisoner.', 'test' => fn ($r) => $escaped($r) && $r->all_prisoners],
            'untouchable' => ['title' => 'Untouchable', 'icon' => '🛡', 'description' => 'Escape without losing a life.', 'test' => fn ($r) => $escaped($r) && $r->lives_lost === 0],
            'speedrunner' => ['title' => 'Speedrunner', 'icon' => '⏱', 'description' => 'Escape in under five minutes of room time.', 'test' => fn ($r) => $escaped($r) && $r->time_ms < 300000],
            'hardened' => ['title' => 'Hardened', 'icon' => '💀', 'description' => 'Escape on hard.', 'test' => fn ($r) => $escaped($r) && $r->difficulty === 'hard'],
            'chameleon' => ['title' => 'Chameleon', 'icon' => '🎭', 'description' => 'Walk out of the gate wearing a guard\'s uniform.', 'test' => fn ($r) => $escaped($r) && $r->disguised_at_end],
            'distraction' => ['title' => 'Distraction Artist', 'icon' => '🪨', 'description' => 'Throw ten stones in one campaign.', 'test' => fn ($r) => $r->stones >= 10],
        ];
    }

    /**
     * Award whatever the result earns that the player does not already hold.
     *
     * @return string[] keys newly earned
     */
    public static function awardFor(CampaignResult $result): array
    {
        $held = static::where('player_name', $result->player_name)->pluck('key')->all();
        $new = [];
        foreach (static::catalogue() as $key => $def) {
            if (in_array($key, $held, true) || ! $def['test']($result)) {
                continue;
            }
            static::create(['player_name' => $result->player_name, 'key' => $key, 'campaign' => $result->campaign, 'earned_at' => now()]);
            $new[] = $key;
        }

        return $new;
    }
}
