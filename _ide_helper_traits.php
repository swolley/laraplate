<?php

declare(strict_types=1);

// ide-helper-traits.php
// PHPStan stub file for trait methods

namespace Modules\Core\Helpers {
    /**
     * @method array getTranslatableFields() Get translatable fields for this model
     * @method bool isTranslatableField(string $field) Check if a field is translatable
     * @method \Illuminate\Database\Eloquent\Relations\HasMany translations() Get the translations relation
     * @method \Illuminate\Database\Eloquent\Relations\HasOne translation() Get the translation for current locale
     * @method self inLocale(string $locale) Set locale context for next assignments
     * @method \Illuminate\Database\Eloquent\Model|null getTranslation(?string $locale = null, ?bool $with_fallback = null) Get translation for specific locale
     * @method self setTranslation(string $locale, array $data) Set translation for specific locale
     * @method self updateTranslation(string $locale, array $data) Update translation for specific locale
     * @method bool hasTranslation(?string $locale = null) Check if translation exists for locale
     * @method \Illuminate\Database\Eloquent\Collection getAllTranslations() Get all translations
     * @method string getCurrentLocale() Get the current locale for setter operations
     *
     * @property string $locale Current locale (accessor)
     */
    trait HasTranslations {}
}

namespace Modules\Cms\Helpers {
    /**
     * @method array fields() Get dynamic fields for this model
     * @method array components() Get components attribute
     * @method void setComponentsAttribute(array $components) Set components attribute
     * @method string getTextualOnlyAttribute() Get textual only attribute
     *
     * @property array<string, mixed> $components Dynamic components
     * @property-read ?string $type Entity type
     * @property-read ?\Modules\Cms\Models\Entity $entity Entity relation
     * @property-read ?\Modules\Cms\Models\Preset $preset Preset relation
     * @property ?int $entity_id Entity ID
     * @property ?int $presettable_id Presettable ID
     */
    trait HasDynamicContents {}
}

namespace Modules\Core\Helpers {
    /**
     * @method array getRules(?string $operation = null) Get validation rules
     * @method bool shouldSkipValidation() Check if validation should be skipped
     * @method void validateWithRules(array $data, ?string $operation = null) Validate data with rules
     */
    trait HasValidations
    {
        public const DEFAULT_RULE = 'always';
    }
}

namespace Modules\Core\Helpers {
    /**
     * @method bool requiresApprovalWhen(string $operation, array $attributes) Check if approval is required
     * @method void approve() Approve pending changes
     * @method void reject() Reject pending changes
     */
    trait HasApprovals {}
}

namespace Modules\Core\Search\Traits {
    /**
     * @method array toSearchableArray() Convert the model instance to a searchable array
     * @method array getSearchMapping() Get the search mapping for this model
     * @method void searchable() Make the model searchable
     * @method void unsearchable() Make the model unsearchable
     * @method \Illuminate\Database\Eloquent\Builder|\Laravel\Scout\Builder search(string $query) Search for models
     * @method string searchableAs() Get the index name for the model
     * @method \Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder makeSearchable() Make the model searchable
     * @method void makeUnsearchable() Make the model unsearchable
     */
    trait Searchable {}
}

namespace Modules\Cms\Helpers {
    /**
     * @method string getSlugAttribute() Get slug attribute
     * @method void setSlugAttribute(string $slug) Set slug attribute
     *
     * @property string $slug Slug attribute
     */
    trait HasSlug {}
}

namespace Modules\Cms\Helpers {
    /**
     * @method string getPathAttribute() Get path attribute
     *
     * @property string $path Path attribute
     */
    trait HasPath {}
}

namespace Modules\Core\Helpers {
    /**
     * @method void restore() Restore a soft-deleted model
     * @method bool trashed() Determine if the model instance has been soft-deleted
     * @method \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model withTrashed() Include soft-deleted models in the results
     * @method \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model onlyTrashed() Retrieve only soft-deleted models
     */
    trait SoftDeletes {}
}

namespace Modules\Core\Helpers {
    /**
     * @method void setHighestOrderNumber() Set the highest order number
     * @method int getHighestOrderNumber() Get the highest order number
     * @method int getLowestOrderNumber() Get the lowest order number
     * @method \Illuminate\Database\Eloquent\Builder scopeOrdered(\Illuminate\Database\Eloquent\Builder $query, string $direction = 'asc') Scope to order by order column
     * @method static void setNewOrder(array|\ArrayAccess $ids, int $startOrder = 1, ?string $primaryKeyColumn = null, ?callable $modifyQuery = null) Set new order for multiple models
     * @method bool shouldSortWhenCreating() Check if should sort when creating
     * @method string determineOrderColumnName() Determine the order column name
     * @method \Illuminate\Database\Eloquent\Builder buildSortQuery() Build query for sorting
     */
    trait SortableTrait {}
}

namespace Spatie\EloquentSortable {
    /**
     * @method void setHighestOrderNumber() Set the highest order number
     * @method int getHighestOrderNumber() Get the highest order number
     * @method int getLowestOrderNumber() Get the lowest order number
     * @method \Illuminate\Database\Eloquent\Builder scopeOrdered(\Illuminate\Database\Eloquent\Builder $query, string $direction = 'asc') Scope to order by order column
     * @method static void setNewOrder(array|\ArrayAccess $ids, int $startOrder = 1, ?string $primaryKeyColumn = null, ?callable $modifyQuery = null) Set new order for multiple models
     * @method bool shouldSortWhenCreating() Check if should sort when creating
     * @method string determineOrderColumnName() Determine the order column name
     * @method \Illuminate\Database\Eloquent\Builder buildSortQuery() Build query for sorting
     */
    trait SortableTrait {}
}
