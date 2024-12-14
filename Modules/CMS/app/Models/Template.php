<?php

namespace Modules\CMS\Models;

use Modules\Core\Helpers\HasVersions;
use Modules\Core\Helpers\HasApprovals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperTemplate
 */
class Template extends Model
{
    use HasFactory, HasApprovals, HasVersions;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'content'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected function casts(): array
    {
        return [
            /*'site_id' => 'integer',*/
            'created_at' => 'immultable_datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
