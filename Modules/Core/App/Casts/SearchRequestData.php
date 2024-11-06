<?php

namespace Modules\Core\App\Casts;

use Modules\Core\App\Http\Requests\SearchRequest;

class SearchRequestData extends ListRequestData
{
	public readonly string $qs;
	/**
	 * @param string|string[] $primaryKey
	 */
	public function __construct(SearchRequest $request, string|null $mainEntity, array $validated, string|array $primaryKey)
	{
		parent::__construct($request, $mainEntity ?? "", $validated, $primaryKey);

		$this->qs = $validated['qs'];
	}
}
