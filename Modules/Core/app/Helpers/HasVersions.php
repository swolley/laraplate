<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Foundation\Auth\User;
use Overtrue\LaravelVersionable\Versionable;

/**
 * @phpstan-type HasVersionsType HasVersions
 */
trait HasVersions
{
	use Versionable;

	protected array $dontVersionable = ['created_at', 'updated_at', 'deleted_at', 'last_login_at'];

	protected function getCreatedBy(): ?User
	{
		$first_version = $this->firstVersion?->{$this->getUserForeignKeyName()};
		return $first_version ? $this->getuser($first_version) : null;
	}

	protected function getModifiedBy(): ?User
	{
		$last_version = $this->lastVersion?->{$this->getUserForeignKeyName()};
		return $last_version ? $this->getuser($last_version) : null;
	}

	private function getuser(int $userId): ?User
	{
		return User::withoutGlobalScopes()->find($userId);
	}
}
