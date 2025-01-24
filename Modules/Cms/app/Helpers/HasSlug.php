<?php

declare(strict_types=1);

namespace Modules\Cms\Helpers;

use Illuminate\Database\Eloquent\Model;

trait HasSlug
{
	public static function bootHasSlug()
	{
		static::saving(function (Model $model) {
			if (!$model->slug || !$model->isDirty(static::slugFields())) {
				$model->slug = $model->generateSlug();
			}
		});
	}

	public static function slugFields(): array
	{
		return ['name'];
	}

	protected function slugValues(): array
	{
		return [$this->name];
	}

	public function generateSlug(): string
	{
		$slugger = config('cms.slugger', '\Illuminate\Support\Str::slug');

		$slug = array_reduce($this->slugValues(), function ($slug, $value) {
			return $slug . '-' . $value;
		}, '');

		return call_user_func($slugger, ltrim($slug, '-'));
	}
}
