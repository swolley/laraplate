<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use LLPhant\Embeddings\Document;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingFormatter\EmbeddingFormatter;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3SmallEmbeddingGenerator;

class GenerateEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [30, 60, 120];
    
    /**
     * Job timeout in seconds
     * 180s (3 min) considering:
     * - 30s per OpenAI call
     * - Multiple calls for long documents
     * - Buffer for network latency and retries
     */
    public $timeout = 300;

    /**
     * Maximum time to wait in queue before execution
     */
    public $maxExceptionsThenWait = 300;

    public $queue = 'embeddings';

    public function __construct(
        private readonly object $model
    ) {}

    public function middleware(): array
    {
        return [
            new ThrottlesExceptions(10, 5), // Max 10 exceptions in 5 minutes
            new RateLimited('embeddings'), // Rate limit for the embeddings queue
        ];
    }

    public function handle(): void
    {
        $data = $this->model->prepareDataToEmbed();
        if (!$data || empty($data)) {
            return;
        }

        try {
            $document = new Document($data);
            $splitDocuments = DocumentSplitter::splitDocument($document, 800);
            $formattedDocuments = EmbeddingFormatter::formatEmbeddings($splitDocuments);
            $embeddingGenerator = new OpenAI3SmallEmbeddingGenerator();
            $embeddedDocuments = $embeddingGenerator->embedDocuments($formattedDocuments);
            
            foreach ($embeddedDocuments as $embeddedDocument) {
                $this->model->embeddings()->create(['embedding' => $embeddedDocument]);
            }
        } catch (\Exception $e) {
            \Log::error('Embedding generation failed for model: ' . get_class($this->model), [
                'model_id' => $this->model->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e; // Rethrow per far fallire il job chain
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('GenerateEmbeddingsJob failed', [
            'model' => get_class($this->model),
            'model_id' => $this->model->id,
            'error' => $exception->getMessage()
        ]);
    }
} 