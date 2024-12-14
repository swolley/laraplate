<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use LLPhant\Embeddings\Document;
use Modules\Core\Models\ModelEmbedding;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingFormatter\EmbeddingFormatter;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3SmallEmbeddingGenerator;

trait Searchable
{
	/** @class-property string[]|null $embed */

	/** 
	 * generate document for Alasticseaerch from entty attributes
	 * @return array<string, mixed>
	 */
	protected function prepareElasticDocument(): array
	{
		foreach ($this->getFillable() as $attribute) {
			$data[$attribute] = $this->{$attribute};
		}

		return $data;
	}

	/**
	 * @return string|null
	 */
	protected function prepareDataToEmbed(): ?string
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

	// Method for generating embeddings
	public function generateEmbeddings()
	{
		$data = $this->prepareDataToEmbed();
		if (!$data || empty($data)) return null;

		$document = new Document($data);
		$splitDocuments = DocumentSplitter::splitDocument($document, 800);
		$formattedDocuments = EmbeddingFormatter::formatEmbeddings($splitDocuments);
		$embeddingGenerator = new OpenAI3SmallEmbeddingGenerator();
		$embeddedDocuments = $embeddingGenerator->embedDocuments($formattedDocuments);
		foreach ($embeddedDocuments as $embeddedDocument) {
			$this->embeddings()->create(['embedding' => $embeddedDocument]);
		}
	}

	protected function prepareDataForElasticsearch(): array
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

	// Index the product in Elasticsearch after generating embeddings
	public function indexInElasticsearch()
	{
		if ($this->isDirty() || !$this->id) {
			throw new \Exception("Model hasn't been saved yet or has pending changes. Indexing aborted.");
		}
		$data = $this->prepareDataForElasticsearch();

		$elasticsearch_client = ClientBuilder::create()->build();
		$elasticsearch_client->index([
			'index' => $this->getTable(), // Name of your Elasticsearch index
			'id' => $this->id,
			'body' => $data
		]);
	}

	public function embeddings(): MorphMany
	{
		return $this->morphMany(ModelEmbedding::class, 'model');
	}
}
