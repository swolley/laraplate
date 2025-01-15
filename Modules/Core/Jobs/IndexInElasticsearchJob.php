<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;

class IndexInElasticsearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [30, 60, 120];

    /**
     * Job timeout in seconds
     * 30s considering:
     * - Single indexing operation
     * - Elasticsearch usually on local network
     * - Buffer for network latency and retries
     */
    public $timeout = 30;

    /**
     * Maximum time to wait in queue before execution
     */
    public $maxExceptionsThenWait = 60;

    public $queue = 'indexing';

    public function __construct(
        private readonly object $model
    ) {}

    public function middleware(): array
    {
        return [
            new ThrottlesExceptions(10, 5),
            new RateLimited('indexing'),
        ];
    }

    public function handle(): void
    {
        try {
            $data = $this->model->toSearchableArray();
            
            $elasticsearch_client = ClientBuilder::create()->build();
            $elasticsearch_client->index([
                'index' => $this->model->searchableAs(),
                'id' => $this->model->id,
                'body' => $data
            ]);
        } catch (\Exception $e) {
            \Log::error('Elasticsearch indexing failed for model: ' . get_class($this->model), [
                'model_id' => $this->model->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('IndexInElasticsearchJob failed', [
            'model' => get_class($this->model),
            'model_id' => $this->model->id,
            'error' => $exception->getMessage()
        ]);
    }
} 