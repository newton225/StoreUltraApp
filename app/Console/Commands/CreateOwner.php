<?php

namespace App\Console\Commands;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

class CreateOwner extends Command
{
    use ProfileValidationRules;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-owner {name : Full name of the owner} {email : Email address used to log in}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an owner account (the password is asked interactively)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $input = [
            'name' => (string) $this->argument('name'),
            'email' => Str::lower((string) $this->argument('email')),
            'password' => password(label: 'Password', required: true),
        ];

        $validator = Validator::make($input, [
            ...$this->profileRules(),
            'password' => ['required', 'string', Password::default()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $owner = new User($input);
        $owner->forceFill([
            'role' => UserRole::Owner,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->components->info("Owner [{$owner->email}] created.");

        return self::SUCCESS;
    }
}
