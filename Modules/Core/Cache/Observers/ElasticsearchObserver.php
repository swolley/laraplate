<?php

declare(strict_types=1);

namespace Modules\Core\Cache\Observers;

use Elastic\Elasticsearch\Client;

class ElasticsearchObserver
{
    public function __construct(private Client $elasticsearchClient)
    {
        // ...
    }

    public function saved($model): void
    {
        $model->elasticSearchIndex($this->elasticsearchClient);
    }

    public function deleted($model): void
    {
        $model->elasticSearchDelete($this->elasticsearchClient);
    }
}
