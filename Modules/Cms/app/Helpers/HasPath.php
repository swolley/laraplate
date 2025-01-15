<?php

declare(strict_types=1);

namespace Modules\Cms\Helpers;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasPath
{
	/** @class-property string|null $slug */

	/**
	 * get prefix for full path
	 * @return string 
	 */
	protected function getPathPrefix(): string
	{
		return $this->getTable();
	}

	/**
	 * get suffix for full path
	 * @return string|null 
	 */
	protected function getPathSuffix(): ?string
	{
		return $this->getKey();
	}

	/**
	 * get path for full path
	 * @return string|null 
	 */
	abstract public function getPath(): ?string;

	/**
	 * get full path
	 * @return string 
	 */
	public function getFullPath(): string
	{
		$suffix = $this->getPathSuffix();
		return $this->getPathPrefix() . '/' . ($this->getPath() ?? 'undefined') . '/' . ($this->slug ?? 'undefined') . ($suffix ? '/' . $suffix : '');
	}

	protected function path(): Attribute
	{
		return Attribute::make(
			get: fn() => $this->getFullPath(),
		);
	}
}
