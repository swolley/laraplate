<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Casts;

use Illuminate\Http\Request;
use Modules\Core\App\Casts\CrudExecutor;

class GridAction extends CrudExecutor
{
	const GET_ALL = parent::SELECT;

	const FUNNELS = 'funnels';

	const OPTIONS = 'options';

	const DATA = 'data';

	const EXPORT = 'export';

	const CHECK = 'check';

	const LAYOUT = 'layout';

	/**
	 * returns if is a write action
	 */
	#[\Override]
	public static function isWriteAction(string $action, ?string $requestMethod = null): bool
	{
		if ($action === static::LAYOUT && $requestMethod !== Request::METHOD_GET) {
			return true;
		}

		return parent::isWriteAction($action);
	}

	/**
	 * returns if is a read action
	 */
	#[\Override]
	public static function isReadAction(string $action, ?string $requestMethod = null): bool
	{
		return !static::isWriteAction($action, $requestMethod);
	}
}
