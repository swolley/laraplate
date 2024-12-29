<?php

declare(strict_types=1);

namespace Modules\Cms\Helpers;

trait HasPath
{
	protected function getPathPrefix(): string
	{
		return $this->getTable();
	}

	protected function getPathSuffix(): ?string
	{
		return $this->getKey();
	}

	abstract public function getPath(): ?string;

	public function getFullPath(): string
	{
		$suffix = $this->getPathSuffix();
		return $this->getPathPrefix() . '/' . ($this->getPath() ?? 'undefined') . '/' . ($this->slug ?? 'undefined') . ($suffix ? '/' . $suffix : '');
	}
}
