<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\ModelEmbedding;
use Elastic\Elasticsearch\ClientBuilder;
use Modules\Core\Jobs\GenerateEmbeddingsJob;
use Modules\Core\Jobs\IndexInElasticsearchJob;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Searchable
{
	/** @class-property string[]|null $embed */
	
	protected static function booted()
    {
        static::created(function (Model $model) {
            $model->dispatchSearchableJobs();
        });

        static::updated(function (Model $model) {
            $model->dispatchSearchableJobs();
        });
    }

	/**
     * Dispatch jobs for embedding generation and elasticsearch indexing
     */
    public function dispatchSearchableJobs(): void
    {
		if (!config('explorer.connection.host')) {
			return;
		}

        if ($this->isDirty() || !$this->id) {
            throw new \Exception("Model hasn't been saved yet or has pending changes. Jobs dispatch aborted.");
        }

		if (config('ai.openai_api_key')) {
			Bus::chain([
				// Generate embeddings
        	    new GenerateEmbeddingsJob($this),
				// Index in Elasticsearch with embeddings
				new IndexInElasticsearchJob($this)
			])->dispatch();
		} else {
			IndexInElasticsearchJob::dispatch($this);
		}
    }

	/** 
	 * generate document for Alasticseaerch from entty attributes
	 * @return array<string, mixed>
	 */
	public function prepareElasticDocument(): array
	{
		foreach ($this->getFillable() as $attribute) {
			$data[$attribute] = $this->{$attribute};
		}

		return $data;
	}

	/**
	 * @return string|null
	 */
	public function prepareDataToEmbed(): ?string
	{
		if (!isset($this->embed) || empty($this->embed)) return null;

		$data = "";
		foreach ($this->embed as $attribute) {
			$value = $this->$attribute;
			if ($value && gettype($value) === "string" && !empty($value)) {
				$data .= ' ' . $value;
			}
		}

		return $data;
	}

	public function toSearchableArray(): array
	{
		$data = $this->prepareElasticDocument();
		$data['connection'] = $this->connection || 'default';
		$data['entity'] = $this->getTable();
		$embeddings = $this->embeddings()->get()->pluck('embedding')->toArray();
		if (!empty($embeddings)) {
			$data['embedding'] = $embeddings;
		}

		return $data;
	}

	public function toSearchableIndex(): array
	{
		$mapping = [];

		$has_geocode = false;

		// Add mappings for fillable attributes
		foreach ($this->getFillable() as $field) {
			if (in_array($field, ['geocode', 'latitude', 'longitude']) && !$has_geocode) {
				$mapping[$field] = ['type' => 'geo_point'];
				$has_geocode = true;
				continue;
			}

			$type = $this->getElasticsearchType($field);

			if ($type !== 'text' || (property_exists($this, 'textOnlyFields') && is_array($this->textOnlyFields) && !in_array($field, $this->textOnlyFields))) {
				$mapping[$field] = ['type' => $type];
				continue;
			}

			$mapping[$field] = [
				'type' => $type,
				'fields' => [
					'keyword' => [
						'type' => 'keyword',
						'ignore_above' => 256
					]
				]
			];
		}

		// Add embedding field mapping if model uses embeddings
		// if (method_exists($this, 'embeddings')) {
		$mapping['embedding'] = [
			'type' => 'dense_vector',
			'dims' => 1536, // OpenAI embedding dimensions
			'index' => true,
			'similarity' => 'cosine'
		];
		// }

		return $mapping;
	}

	/**
	 * Get Elasticsearch field type based on model's cast type
	 */
	protected function getElasticsearchType(string $field): string
	{
		$cast_type = $this->getCasts()[$field] ?? null;
		return match ($cast_type) {
			'integer', 'int' => 'long',
			'float', 'double', 'decimal' => 'double',
			'boolean', 'bool' => 'boolean',
			'datetime', 'date', 'timestamp' => 'date',
			'array', 'json', 'object', 'collection' => 'object',
				// 'latitude', 'longitude' => 'geo_point',
				// 'string', 'text' => 'text',
			default => 'text'
		};
	}

	public function searchableAs(): string
	{
		return $this->getTable();
	}

	public function createIndex()
	{
		$elasticsearch_client = ClientBuilder::create()->build();
		$index_name = $this->searchableAs();
		$temp_index = $index_name . '_temp_' . time();

		try {
			$new_index = [
				'index' => $index_name,
				'body' => [
					'mappings' => [
						'properties' => $this->toSearchableIndex()
					]
				]
			];

			$index_exists = $elasticsearch_client->indices()->exists(['index' => $index_name]);


			if (!$index_exists) {
				$elasticsearch_client->indices()->create($new_index);
				Log::info('Elasticsearch \'{index}\' index created', [
					'index' => $index_name,
				]);
				return;
			}

			$current_mapping = $elasticsearch_client->indices()->getMapping(['index' => $index_name]);

			if (json_encode($current_mapping[$index_name]['mappings']) === json_encode($new_index['body']['mappings'])) {
				return;
			}

			$new_index['index'] = $temp_index;
			$elasticsearch_client->indices()->create($new_index);
			$elasticsearch_client->reindex([
				'body' => [
					'source' => ['index' => $index_name],
					'dest' => ['index' => $temp_index]
				]
			]);

			$elasticsearch_client->indices()->delete(['index' => $index_name]);
			$elasticsearch_client->indices()->putAlias([
				'index' => $temp_index,
				'name' => $index_name
			]);
			Log::info('Elasticsearch \'{index}\' index updated', [
				'index' => $index_name,
			]);
		} catch (\Exception $e) {
			// Cleanup in caso di errore
			if ($elasticsearch_client->indices()->exists(['index' => $temp_index])) {
				$elasticsearch_client->indices()->delete(['index' => $temp_index]);
			}

			Log::error('Elasticsearch \'{index}\' index creation failed', [
				'index' => $index_name,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);
			throw $e;
		}
	}

	public function embeddings(): MorphMany
	{
		return $this->morphMany(ModelEmbedding::class, 'model');
	}
}
