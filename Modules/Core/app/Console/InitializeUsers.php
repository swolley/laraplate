<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Support\Str;
use Modules\Core\Models\Role;
use Illuminate\Console\Command;
use function Laravel\Prompts\text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use function Laravel\Prompts\password;

class InitializeUsers extends Command
{
	protected $signature = 'auth:initialize-users';

	protected $description = 'Initialize users. <comment>(⛭ Modules\Core)</comment>';

	public function handle(): void
	{
		$user_class = user_class();

		$root = 'root';
		$admin = 'admin';
		$anonymous = 'anonymous';

		$groups = Role::all()->keyBy('name');

		DB::transaction(function () use ($admin, $root, $anonymous, $user_class, $groups) {
			if (!$user_class::whereName($root)->exists()) {
				$email = text("Please specify a $root user email", required: true, validate: fn(string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Please type a valid email');
				$password = password("Please specify a $root user password", required: true);
				password("Please confirm the password", required: true, validate: fn(string $value) => $password !== $value ? 'Passwords don\'t match' : null);
				$root_user = $user_class::make([
					'name' => $root,
					'username' => $root,
					'email' => $email,
					'password' => Hash::make($password),
				]);
				$root_user->email_verified_at = now();
				$root_user->locked_at = now();
				$root_user->save();
				// @phpstan-ignore-next-line
				$root_user->assignRole($groups['superadmin']);
				$this->info("$root created");
			} else {
				$this->info("$root already exists");
			}

			if (!$user_class::whereName($admin)->exists()) {
				$email = text("Please specify a $admin user email or leave blank to skip", required: false, validate: fn(string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Please type a valid email');
				if ($email) {
					$password = password("Please specify a $admin user password", required: true);
					password("Please confirm the password", required: true, validate: fn(string $value) => $password !== $value ? 'Passwords don\'t match' : null);
					$admin_user = $user_class::make([
						'name' => $admin,
						'username' => $admin,
						'email' => $email,
						'password' => Hash::make(config('app.name')),
					]);
					$admin_user->email_verified_at = now();
					$admin_user->save();
					// @phpstan-ignore-next-line
					$admin_user->assignRole($groups[$admin]);
					$this->info("$admin created");
				}
			} else {
				$this->info("$admin already exists");
			}

			if (!$user_class::whereName($anonymous)->exists()) {
				$anonymous_user = $user_class::make([
					'name' => $anonymous,
					'username' => $anonymous,
					'email' => "$anonymous@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
					'password' => Hash::make(config('app.name')),
				]);
				$anonymous_user->email_verified_at = now();
				$anonymous_user->save();
				// @phpstan-ignore-next-line
				$anonymous_user->assignRole($groups['guest']);
				$this->info("$anonymous created");
			} else {
				$this->info("$anonymous already exists");
			}
		});
	}
}
