<?php

declare(strict_types=1);

namespace Modules\Cms\Casts;

enum FieldType: string
{
	case TEXT = 'text';
	case TEXTAREA = 'textarea';
	case SWITCH = 'switch';
	case SELECT = 'select';
	case RADIO = 'radio';
	case CHECKBOX = 'checkbox';
	case DATETIME = 'datetime';
	case NUMBER = 'number';
	case JSON = 'json';

	public function getRule(): string
	{
		return match ($this) {
			self::TEXT, self::TEXTAREA => 'string',
			self::SWITCH => 'boolean',
			self::CHECKBOX => 'array',
			// self::RADIO => 'string',
			// self::SELECT => 'string',
			self::DATETIME => 'datetime',
			self::NUMBER => 'number',
			self::JSON => 'json',
			default => '',
		};
	}
}
