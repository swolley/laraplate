<?php

declare(strict_types=1);

namespace Modules\Core\App\Models;

use Illuminate\Validation\Rule;
use Approval\Models\Modification;
use Approval\Traits\ApprovesChanges;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Modules\Core\App\Models\License;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Locking\Traits\HasLocks;
use Lab404\Impersonate\Models\Impersonate;
use Modules\Core\App\Helpers\HasValidations;
use Modules\Core\App\Observers\UserObserver;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Modules\Core\App\Helpers\HasCommonObserver;
use Modules\Core\Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Lab404\Impersonate\Services\ImpersonateManager;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

#[ObservedBy([UserObserver::class])]
class User extends Model implements AuthenticatableContract, AuthorizableContract, CanResetPasswordContract
{
    use ApprovesChanges,
        Authenticatable,
        Authorizable,
        CanResetPassword,
        // HasApiTokens,
        HasCommonObserver,
        HasFactory,
        HasLocks,
        HasValidations,
        Impersonate,
        MustVerifyEmail,
        TwoFactorAuthenticatable,
        Notifiable,
        SoftDeletes,
        HasRoles {
        roles as defaultRoles;
        permissions as defaultPermissions;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     *
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'lang'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     *
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected $hidden = [
        'password',
        'remember_token',
        'pivot',
        'licence_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->rules[static::DEFAULT_RULE] = [
            'lang' => 'nullable|in:' . implode(',', translations()),
            'locked_at' => 'date|nullable',
        ];
        $this->rules['create'] = [
            'name' => 'string|required|max:255',
            'username' => 'max:255|unique:' . static::class,
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users'),
            ],
            'password' => [Password::required()],
        ];
        $this->rules['update'] = [
            'name' => 'string|max:255',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function isGuest(): bool
    {
        return !isset($this->email);
    }

    public function canImpersonate(): bool
    {
        return $this->isSuperAdmin() || $this->hasPermissionViaRole(Permission::findByName(($this->getConnectionName() ?? 'default') . $this->getTable() . '.impersonate'));
    }

    public function getImpersonator(): self
    {
        return $this->isImpersonated() ? app(ImpersonateManager::class)->getImpersonator() : $this;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    public function permissions(): BelongsToMany
    {
        return $this->defaultPermissions();
    }

    public function roles(): BelongsToMany
    {
        return $this->defaultRoles();
    }

    public function grid_configs(): HasMany
    {
        return $this->hasMany(UserGridConfig::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    protected function authorizedToApprove(Modification $mod): bool
    {
        return $this->can(($this->getConnectionName() ?? 'default') . $this->getTable() . '.approve');
    }

    protected function authorizedToDisapprove(Modification $mod): bool
    {
        return $this->can(($this->getConnectionName() ?? 'default') . $this->getTable() . '.disapprove');
    }

    protected function getDefaultGuardName(): string
    {
        return 'web';
    }
}
