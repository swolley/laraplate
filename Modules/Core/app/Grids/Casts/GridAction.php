<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Casts;

use Modules\Core\Casts\ActionEnum;

enum GridAction: string
{
	case SELECT = ActionEnum::SELECT->value;
	case INSERT = ActionEnum::INSERT->value;
	case UPDATE = ActionEnum::UPDATE->value;
	case DELETE = ActionEnum::DELETE->value;
		// case RESTORE = 'restore';
	case FORCE_DELETE = ActionEnum::FORCE_DELETE->value;
	case APPROVE = ActionEnum::APPROVE->value;
		// case DISAPPROVE = 'disapprove';
		// case IMPERSONATE = 'impersonate';
	case LOCK = ActionEnum::LOCK->value;
		// case UNLOCK = 'unlock';
	case FUNNELS = 'funnels';
	case OPTIONS = 'options';
	case DATA = 'data';
	case EXPORT = 'export';
	case CHECK = 'check';
	case GET_ALL = 'get_all';

	public static function values(): array
	{
		return array_map(fn($case) => $case->value, static::cases());
	}

	/**
	 * returns if is a read action
	 */
	public static function isReadAction(GridAction | string $action): bool
	{
		if ($action instanceof GridAction) {
			$action = $action->value;
		}

		return match ($action) {
			static::INSERT->value, static::UPDATE->value, static::DELETE->value, static::FORCE_DELETE->value, static::APPROVE->value, static::LOCK->value => false,
			default => true,
		};
	}

	/**
	 * returns if is a write action
	 */
	public static function isWriteAction(GridAction | string $action): bool
	{
		return !static::isReadAction($action);
	}
}
