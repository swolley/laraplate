<?php

namespace Modules\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Cms\Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Cms\Helpers\HasPath;
use Modules\Core\Cache\Searchable;

/**
 * @mixin IdeHelperLocation
 */
class Location extends Model
{
    use HasFactory, Searchable, HasPath {
        prepareElasticDocument as prepareElasticDocumentTrait;
    }

    protected array $textOnlyFields = ['address', 'city', 'province', 'country', 'postcode'];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'address',
        'city',
        'province',
        'country',
        'postcode',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }

    public function prepareElasticDocument(): array
	{
		$document = $this->prepareElasticDocumentTrait();
        $document['id'] = $this->id;
        $document['geocode'] = [
            'lat' => (float)$this->latitude,
            'lon' => (float)$this->longitude
        ];
        unset($document['latitude'], $document['longitude']);

		return $document;
	}

    // protected function slugFields(): array
	// {
	// 	return ['name'];
	// }

    public function getPathPrefix(): string
	{
		return '';
	}

	#[\Override]
	public function getPath(): ?string
	{
		return '';
	}
}