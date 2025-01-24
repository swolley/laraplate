<?php

namespace Modules\Cms\Http\Controllers;

use Throwable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use UnexpectedValueException;
use Modules\Cms\Models\Content;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Http\Requests\ListRequest;
use Symfony\Component\HttpFoundation\Response;
use Modules\Core\Http\Controllers\CrudController;
use Illuminate\Contracts\Container\BindingResolutionException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class ContentsController extends CrudController
{
    /**
     * Get contents by tag
     * 
     * @param ListRequest $request 
     * @param string $relation relation name
     * @param string $value relation value
     * @param string $entity entity name or slug
     * @return Response 
     * @throws BadRequestException 
     * @throws InvalidArgumentException 
     * @throws BindingResolutionException 
     * @throws Throwable 
     * @throws UnexpectedValueException 
     */
    public function getContentsByRelation(ListRequest $request, string $relation, string $value, string $entity)
    {
        $filters = $this->createCommonFilters($request, $relation, $value);
        
        if ($entity !== 'contents') {
            $entity = Str::singular($entity);
            $filters['filters'][] = [
                'operator' => WhereClause::OR->value,
                'filters' => [
                    [
                        'property' => 'contents.entity.name',
                        'value' => $entity,
                        'operator' => FilterOperator::EQUALS->value,
                    ],
                    [
                        'property' => 'contents.entity.slug',
                        'value' => $entity,
                        'operator' => FilterOperator::EQUALS->value,
                    ],
                ],
            ];
        }

        $request->merge([
            'entity' => 'contents',
            'filters' => $filters,
        ]);
        
        return $this->list($request);
    }

    private function createCommonFilters(ListRequest $request, string $relation, string $value): array
    {
        $filters = $request->get('filters');
        $filters = [
            'operator' => WhereClause::AND->value,
            'filters' => $filters ?? [],
        ];

        if (!method_exists(Content::class, $relation)) {
            $relation = Str::endsWith($relation, 's') ? Str::singular($relation) : Str::plural($relation);
        }
        if (!method_exists(Content::class, $relation)) {
            throw new BadRequestException('Invalid relation');
        }

        $filters = [
            'operator' => WhereClause::AND->value,
            'filters' => [
                [
                    'operator' => WhereClause::OR->value,
                    'filters' => [
                        [
                            'property' => "contents.{$relation}.name",
                            'value' => $value,
                            'operator' => FilterOperator::EQUALS->value,
                        ],
                        [
                            'property' => "contents.{$relation}.slug",
                            'value' => $value,
                            'operator' => FilterOperator::EQUALS->value,
                        ],
                    ],
                ],
            ],
        ];

        return $filters;
    }
}