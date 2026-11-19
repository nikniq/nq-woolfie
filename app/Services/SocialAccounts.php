<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Finds or creates the local account for someone signing in with Google or nikniq.com.
 */
class SocialAccounts
{
    /**
     * Match on the provider id first, then link an existing account with the same email,
     * otherwise create a new account with a unique player name. Null when there is no email.
     */
    public function findOrCreate(string $provider, string $id, ?string $email, ?string $displayName): ?User
    {
        $column = "{$provider}_id";

        if ($user = User::where($column, $id)->first()) {
            return $user;
        }

        $email = Str::lower((string) $email);
        if ($email === '') {
            return null;
        }

        if ($user = User::where('email', $email)->first()) {
            $user->forceFill([$column => $id, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

            return $user;
        }

        $user = new User(['name' => $this->uniqueName($displayName ?: Str::before($email, '@')), 'email' => $email]);
        $user->forceFill([$column => $id, 'email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * Turn a display name into a free player name that passes the registration rules.
     */
    public function uniqueName(string $wanted): string
    {
        $base = trim(Str::limit(preg_replace('/[^\pL\pN _.-]+/u', '', $wanted) ?? '', 20, ''));
        if (mb_strlen($base) < 2) {
            $base = 'Player';
        }

        $name = $base;
        for ($n = 2; User::where('name', $name)->exists(); $n++) {
            $name = $base.' '.$n;
        }

        return $name;
    }
}
