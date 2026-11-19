<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'app:make-admin {email : Email of the user to promote} {--revoke : Remove admin access instead}';

    protected $description = 'Grant or revoke admin access for a user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();
        $this->info($user->is_admin ? "{$user->name} is now an admin." : "{$user->name} is no longer an admin.");

        return self::SUCCESS;
    }
}
