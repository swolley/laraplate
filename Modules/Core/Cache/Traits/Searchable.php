<?php

declare(strict_types=1);

namespace Modules\Core\Cache\Traits;

use Elastic\Elasticsearch\Client;
use Modules\Core\Cache\Observers\ElasticsearchObserver;

trait Searchable
{
    abstract public function getSearchFields(): array;
    public static function bootSearchable(): void
    {
        if (config('services.search.enabled')) {
            static::observe(ElasticsearchObserver::class);
        }
    }

    public function elasticsearchIndex(Client $elasticsearchClient): void
    {
        $elasticsearchClient->index([
            'index' => $this->getTable(),
            'type' => '_doc',
            'id' => $this->getKey(),
            'body' => $this->toElasticsearchDocumentArray(),
        ]);
    }

    public function elasticsearchDelete(Client $elasticsearchClient): void
    {
        $elasticsearchClient->delete([
            'index' => $this->getTable(),
            'type' => '_doc',
            'id' => $this->getKey(),
        ]);
    }

    public function getSearchIndex(): string
    {
        return $this->getTable();
    }

    public function getSearchKey(): string
    {
        return $this->getKey();
    }

    public function getSearchType(): string
    {
        return '_doc';
    }

    public function getSearchArray(): array
    {
        $mapped = array_flip($this->getSearchFields());

        foreach ($mapped as $key => &$value) {
            $value = $this->{$key};
        }

        return $mapped;
    }
}
