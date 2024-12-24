<?php

namespace Modules\CMS\Helpers;

use Illuminate\Database\Eloquent\Model;

trait HasSlug
{
	/**
	 * 
	 * @var array<string>
	 */
	public array $slug_fields = ['name'];

	public static function bootHasSlug()
	{
		static::saving(function (Model $model) {
			if (!$model->slug || !$model->isDirty($model->slug_fields)) {
				$model->slug = $model->generateSlug();
			}
		});
	}

	public function generateSlug(): string
	{
		$slugger = config('cms.slugger', '\Illuminate\Support\Str::slug');

		$slug = array_reduce($this->slug_fields, function ($slug, $field) {
			return $slug . '-' . $this->{$field};
		}, '');

		return call_user_func($slugger, $slug);
	}
}
