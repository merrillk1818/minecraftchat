<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteAdmin extends Command
{
    protected $signature = 'admin:promote {email : The user\'s email address}';
    protected $description = 'Promote a registered user to admin role';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user  = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        if ($user->isAdmin()) {
            $this->warn("{$user->name} ({$email}) is already an admin.");
            return self::SUCCESS;
        }

        $user->update(['role' => 'admin']);
        $this->info("Promoted {$user->name} ({$email}) to admin.");

        return self::SUCCESS;
    }
}
