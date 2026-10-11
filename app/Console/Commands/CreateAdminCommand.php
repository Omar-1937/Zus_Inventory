<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Controlled way to create the first administrator account.
 */
class CreateAdminCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'zus:create-admin
                            {--name= : Full name}
                            {--email= : Email address}
                            {--password= : Password (prompted securely if omitted)}';

    /**
     * @var string
     */
    protected $description = 'Create an ADMIN account for the ZUS Coffee Inventory system';

    public function handle(AuditLogger $audit): int
    {
        $data = [
            'full_name' => $this->option('name') ?: text('Full name', required: true),
            'email' => $this->option('email') ?: text('Email address', required: true),
            'password' => $this->option('password') ?: password('Password (min. 8 characters, letters and numbers)', required: true),
        ];

        $validator = Validator::make($data, [
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create($data + ['role' => UserRole::Admin, 'is_active' => true]);
        $audit->log('CREATE', $user, details: ['via' => 'zus:create-admin', 'role' => 'ADMIN'], user: $user);

        $this->components->info("Administrator {$user->full_name} <{$user->email}> created.");

        return self::SUCCESS;
    }
}
