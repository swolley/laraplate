<?php

declare(strict_types=1);

namespace Modules\Core\App\Console;

use Throwable;
use Illuminate\Console\Command;
use Modules\Core\App\Models\Role;
use Modules\Core\App\Models\User;
use function Laravel\Prompts\text;
use Illuminate\Support\Facades\Hash;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use Modules\Core\App\Models\Permission;
use function Laravel\Prompts\multiselect;
use Illuminate\Support\Facades\Validator;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'auth:create-user';

    /**
     * The console command description.
     */
    protected $description = 'Create new user <comment>(Modules\Core)</comment>';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $total_users_created = 0;

        try {
            /** @var User */
            $user = new (user_class());
            $fillables = $user->getFillable();
            $validations = $user->getOperationRules('create');
            $all_roles = Role::get(['id', 'name'])->pluck('name', 'id');
            $all_permissions = Permission::get(['id', 'name'])->pluck('name', 'id');

            do {
                foreach ($fillables as $attribute) {
                    if ($attribute !== 'password') {
                        $answer = text(ucfirst($attribute), required: true, validate: fn ($value) => $this->validationCallback($attribute, $value, $validations));
                    } else {
                        $answer = password(ucfirst($attribute), required: true, validate: fn ($value) => $this->validationCallback($attribute, $value, $validations));
                        password("Confirm {$attribute}", required: true, validate: fn ($value) => $this->validationCallback($attribute, $value, ['password' => "in:{$answer}"]));
                        $answer = Hash::make($answer);
                    }

                    $user->{$attribute} = $answer;
                }
                $roles = multiselect('Roles', $all_roles, required: true);
                $permissions = (confirm('Do you want to specify custom user permissions', false, hint: "user already inherits choosen Roles permissions"))
                    ? multiselect('Permissions', $all_permissions, required: false)
                    : [];

                $user->save();
                $user->roles()->sync($roles);

                if (!empty($permissions)) {
                    $user->permissions()->sync($permissions);
                }
                $this->output->info("User created");
                $total_users_created++;
            } while (confirm('Do you want to create another user?', false));

            $this->output->info("Creatd {$total_users_created} users");

            return static::SUCCESS;
        } catch (Throwable $ex) {
            $this->error($ex->getMEssage());

            return static::FAILURE;
        }
    }
    private function validationCallback(string $attribute, string $value, array $validations)
    {
        if (!array_key_exists($attribute, $validations)) {
            return null;
        }
        $validator = Validator::make([$attribute => $value], array_filter($validations, fn ($k) => $k === $attribute, ARRAY_FILTER_USE_KEY))->stopOnFirstFailure(true);

        if (!$validator->passes()) {
            return $validator->messages()->first();
        }

        return null;
    }
}
