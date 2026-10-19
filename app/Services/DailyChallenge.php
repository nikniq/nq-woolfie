<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * One fixed challenge per calendar day: two modifiers picked from the date,
 * the same for everyone, so daily scores are comparable.
 */
class DailyChallenge
{
    public const MODIFIERS = [
        'no_gun' => ['label' => 'Unarmed', 'description' => 'The pistol has been removed from the castle.'],
        'no_stones' => ['label' => 'Empty pockets', 'description' => 'No stones to throw.'],
        'no_hiding' => ['label' => 'Nowhere to hide', 'description' => 'The crates have been cleared away.'],
        'extra_guard' => ['label' => 'Reinforced', 'description' => 'Every room has one more guard on patrol.'],
        'fast_guards' => ['label' => 'Double time', 'description' => 'Guards move 20% faster.'],
        'fog' => ['label' => 'Fog of war', 'description' => 'You can only see a short way around you.'],
        'fragile' => ['label' => 'One life', 'description' => 'Get caught once and the run is over.'],
        'lockdown_start' => ['label' => 'Already compromised', 'description' => 'The castle starts on lockdown.'],
        'double_gold' => ['label' => 'Rich pickings', 'description' => 'Gold is worth double.'],
        'eagle_eyes' => ['label' => 'Eagle eyes', 'description' => 'Guards see 30% further.'],
    ];

    /** @return array{date: string, title: string, modifiers: array<int, array{key: string, label: string, description: string}>} */
    public function forDate(?Carbon $date = null): array
    {
        $date = ($date ?? Carbon::now())->toDateString();
        $keys = array_keys(self::MODIFIERS);
        // deterministic pick of two distinct modifiers from the date
        $seed = crc32('woolfie-daily-'.$date);
        $first = $keys[$seed % count($keys)];
        $second = $keys[intdiv($seed, 97) % (count($keys) - 1)];
        if ($second === $first) {
            $second = $keys[(array_search($second, $keys, true) + 1) % count($keys)];
        }
        $picked = [$first, $second];
        if (in_array('fragile', $picked, true) && in_array('lockdown_start', $picked, true)) {
            $picked = ['fragile', 'double_gold']; // that pair is just cruel
        }

        return [
            'date' => $date,
            'title' => implode(' + ', array_map(fn ($k) => self::MODIFIERS[$k]['label'], $picked)),
            'modifiers' => array_map(fn ($k) => ['key' => $k] + self::MODIFIERS[$k], $picked),
        ];
    }
}
