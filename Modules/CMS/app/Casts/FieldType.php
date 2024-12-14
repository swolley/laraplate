<?php

declare(strict_types=1);

namespace Modules\CMS\Casts;

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
}
