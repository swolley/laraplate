<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Foundation\Auth\User;
// use Thiagoprz\CompositeKey\HasCompositeKey;
use Illuminate\Database\Eloquent\Model;
use Overtrue\LaravelVersionable\Version;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;
// use Illuminate\Database\Eloquent\Relations\Pivot;
// use Illuminate\Database\Eloquent\Relations\MorphMany;
// use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @phpstan-type HasVersionsType HasVersions
 */
trait HasVersions
{
	use Versionable;

	protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

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

	private function getUser(int $userId): ?User
	{
		$user_class = user_class();
		return $user_class::withoutGlobalScopes()->find($userId);
	}

	/**
	 * @param  string|\DateTimeInterface|null  $time
	 *
	 * @throws \Carbon\Exceptions\InvalidFormatException
	 */
	public function createVersion(array $replacements = [], $time = null): ?Version
	{
		if ($this->shouldBeVersioning() || ! empty($replacements)) {
			return tap(config('versionable.version_model')::createForModel($this, $replacements, $time), function () {
				$this->removeOldVersions((int) $this->getKeepVersionsCount());
			});
		}

		return null;
	}

	public function createInitialVersion(Model $model): Version
	{
		/** @var \Overtrue\LaravelVersionable\Versionable|Model $refreshedModel */
		$refreshedModel = static::query()->withoutGlobalScopes()->findOrFail($model->getKey());

		/**
		 * As initial version should include all $versionable fields,
		 * we need to get the latest version from database.
		 * so we force to create a snapshot version.
		 */
		$attributes = $refreshedModel->getVersionableAttributes(VersionStrategy::SNAPSHOT);

		return config('versionable.version_model')::createForModel($refreshedModel, $attributes, $refreshedModel->updated_at);
	}

	// // TODO: sarà sicuramente da overridare per via delle primary key multiple
	// public function versions(): MorphMany
	// {
	// 	$version_model = $this->getVersionModel();
	// 	if (class_uses_trait($version_model, HasCompositeKey::class) || is_a($version_model, Pivot::class)) {
	// 		//TODO: da finire di scrivere, devo in qualche modo 
	// 		return $this->morphMany($version_model, 'versionable', 'versionable_type', 'versionable_id');
	// 	}
	// 	return $this->morphMany($version_model, 'versionable');
	// }
}
