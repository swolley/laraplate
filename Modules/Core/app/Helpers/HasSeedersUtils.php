<?php

namespace Modules\Core\Helpers;

use Modules\Core\Helpers\HasApprovals;
use Illuminate\Database\Eloquent\Model;

trait HasSeedersUtils
{
	/** 
	 * @param class-string $class 
	 */
	private function create(string $class, array $attributes): Model
	{
		$model = $class::make($attributes);
		if (class_uses_trait($model, HasApprovals::class)) $model->setForcedApprovalUpdate(true);
		$model->save();
		return $model;
	}

	/**
	 * @param class-string $model
	 * @return void 
	 */
	private function logOperation(string $model): void
	{
		$already_exists = $model::query()->exists();
		$table = (new $model)->getTable();
		$this->command->line("  " . ($already_exists ? 'Updating' : 'Creating') . ' default <fg=cyan;options=bold>' . $table . '</>');
	}
}
