<?php

declare(strict_types=1);

namespace Modules\Core\Cache\Repositories;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Elastic\Elasticsearch\Client;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\App\Http\Requests\ListRequest;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Cache\Traits\Searchable;

class ElasticsearchRepository
{
    /**
     * @var Client
     */
    private $elasticsearch;

    public function __construct(Client $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    public function search(string $model, string|ListRequest $query = ''): Collection
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $items = $this->searchOnElasticsearch($model, $query);

        return $this->buildCollection($model, $items);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @return (int[]|mixed)[]
     *
     * @psalm-suppress InvalidDocblock
     */
    private function searchOnElasticsearch(string $model, string|ListRequest $query): array
    {
        if (!class_exists($model)) {
            throw new InvalidArgumentException("{$model} doesn't exists");
        }

        if (!class_uses_trait($model, Searchable::class)) {
            throw new InvalidArgumentException("{$model} doesn't uses Searchable trait");
        }

        /**
         * @psalm-suppress UndefinedDocblockClass
         *
         * @var Model|Searchable
         */
        /** @psalm-suppress UnsafeInstantiation */
        $model_instance = new $model();

        /** @psalm-suppress UndefinedDocblockClass */
        $searchable_fields = $model_instance->getSearchableFields();
        $discarded_fields = [];

        /** @psalm-suppress UndefinedDocblockClass */
        $params = [
            'index' => $model_instance->getSearchIndex(),
            'type' => $model_instance->getSearchType(),
            'body' => [
                'query' => [],
            ],
        ];

        if (is_string($query)) {
            $params['body']['query'] = [
                // andrebbero fatte in like se sono testi, il resto equals con i dovuti parse di date, booleani, ecc
                'multi_match' => [
                    'fields' => [$searchable_fields /* 'title^5', 'body', 'tags' */],
                    'query' => $query,
                ],
            ];
        } else {
            $filters = $query->validated()['filters'];
            $discarded_fields = array_flip(array_diff(array_keys($filters), $searchable_fields));

            /** @var string $key */
            foreach ($discarded_fields as $key => &$value) {
                $value = $query[$key];
                unset($query[$key]);
            }

            $params['body']['query'] = [
                'bool' => [],
            ];

            // TODO: da scrivere sulla base del query builer
            // se è "!=" deve essere $params['body']['query']['bool']['must_not']['match'][] = [FIELD => VALUE]
            // se è "=" deve essere $params['body']['query']['bool']['must']['match'][] = [FIELD => VALUE]
            // se è "like" deve essere $params['body']['query']['bool']['should']['match'][] = [FIELD => VALUE]
            // se è "between", ">", ">=", "<", "<=" devono essere $params['body']['query']['bool']['range'][FIELD] = [OPERATOR (lt, lte, t, gte) => VALUE, ...]
            // VERIFICARE IL match_all a cosa serve
            // foreach($filters as $key => $value) {
            // 	if($value['operator'])
            // }
        }

        /** @psalm-suppress PossiblyUndefinedMethod */
        $items = $this->elasticsearch->search($params)->wait();

        return [
            'discarded_fields' => $discarded_fields,
            'items' => $items,
        ];
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function buildCollection(string $model, array $items): Collection
    {
        $ids = Arr::pluck($items['hits']['hits'], '_id');

        return $model::findMany($ids)/* ->sortBy(fn ($article) => array_search($article->getKey(), $ids)) */;
    }
}
