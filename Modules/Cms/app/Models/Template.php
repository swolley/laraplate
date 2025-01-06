<?php

namespace Modules\Cms\Models;

use Modules\Core\Helpers\HasVersions;
use Modules\Core\Helpers\HasApprovals;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasValidations;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperTemplate
 */
class Template extends Model
{
    use HasFactory, /*HasApprovals,*/ HasVersions, HasValidations {
        getRules as protected getRulesTrait;
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name', 
        'content',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            /*'site_id' => 'integer',*/
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function getRules(): array
    {
        $rules = $this->getRulesTrait();
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255', 'unique:templates,name'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255', 'unique:templates,name,' . $this->id],
        ]);
        return $rules;
    }
}
