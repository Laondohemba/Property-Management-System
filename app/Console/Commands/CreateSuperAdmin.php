<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Organization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role as RoleModel;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Bootstraps an organisation and its first Super Admin. Staff accounts are otherwise created by
 * invitation (SYS-01), so this is the only way to create the very first user.
 */
#[Signature('pms:create-super-admin {--organization=} {--name=} {--email=}')]
#[Description('Create an organisation and its first Super Admin user')]
class CreateSuperAdmin extends Command
{
    public function handle(): int
    {
        $input = [
            'organization' => $this->option('organization') ?: text('Organisation name', required: true),
            'name' => $this->option('name') ?: text('Full name', required: true),
            'email' => $this->option('email') ?: text('Email', required: true),
            'password' => password('Password', required: true, hint: 'At least 12 characters with mixed case, a number and a symbol.'),
        ];

        $validator = Validator::make($input, [
            'organization' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($input): User {
            $organization = Organization::create([
                'name' => $input['organization'],
                'slug' => Str::slug($input['organization']).'-'.Str::lower(Str::random(6)),
            ]);

            $user = $organization->users()->create([
                'name' => $input['name'],
                'email' => Str::lower($input['email']),
                'password' => $input['password'],
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(RoleModel::findOrCreate(Role::SuperAdmin->value, 'web'));

            return $user;
        });

        $this->components->info("Super Admin {$user->email} created for {$input['organization']}.");

        return self::SUCCESS;
    }
}
