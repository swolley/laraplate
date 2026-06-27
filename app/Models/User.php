<?php

declare(strict_types=1);

namespace App\Models;

use Modules\Core\Models\User as CoreUser;

/**
 * @property string|null $name
 * @property string $email
 *
 * @mixin \Eloquent
 * @mixin IdeHelperUser
 */
final class User extends CoreUser
{
    //
}
