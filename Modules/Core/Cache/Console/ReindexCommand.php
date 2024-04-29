<?php

declare(strict_types=1);

namespace Modules\Core\Cache\Console;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Elastic\Elasticsearch\ClientBuilder;
use Modules\Core\Cache\Traits\Searchable;

class ReindexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:elastic-reindex {--model: model to be reindexed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Indexes all articles to Elasticsearch <comment>(Modules\Core)</comment>';

    public function handle(): int
    {
        try {
            $elasticsearch = ClientBuilder::fromConfig(config('services.search'));

            if (!$elasticsearch->ping()->asBool()) {
                throw new Exception('No running Elasticsearch cluster');
            }

            $model = $this->argument('model');

            /** @var string $model */
            $model = head(array_filter(models(), fn ($m) => Str::endsWith($m, '\\' . $model)));

            if (!$model) {
                throw new Exception("No model found with name {$model}");
            }

            if (!class_uses_trait($model, Searchable::class)) {
                throw new Exception("Model {$model} doens'nt uses Searchable trait");
            }

            $this->info("Indexing {$model::count()} {$model} records. This might take a while...");

            $params = ['body' => []];
            $i = 0;

            foreach ($model::cursor() as $record) {
                $params['body'][] = [
                    'index' => [
                        '_index' => $record->getSearchIndex(),
                        '_id' => $record->getSearchType(),
                    ],
                ];

                $params['body'][] = $record->toSearchArray();

                $this->output->write('.');

                if ($i % 100 == 0) {
                    /** @psalm-suppress InvalidArgument */
                    $responses = $elasticsearch->bulk($params);

                    // erase the old bulk request
                    $params = ['body' => []];

                    // unset the bulk response when you are done to save memory
                    unset($responses);
                }

                $i++;
            }

            /** @psalm-suppress InvalidArgument */
            if (!empty($params['body'])) {
                $elasticsearch->bulk($params);
            }

            $this->info("\nDone!");

            return static::SUCCESS;
        } catch (Exception $ex) {
            $this->error($ex->getMessage());
            Log::error($ex->getMessage());

            return static::FAILURE;
        }
    }
}
