<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Modules\Core\Models\Permission;
use Illuminate\Http\Resources\Json\JsonResource;

class UserInfoResponse extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        if ($this->resource) {
            $permissions = [];

            foreach ($this->resource->isSuperAdmin() ? Permission::all() : $this->resource->getAllPermissions() as $permission) {
                if (!isset($permissions[$permission->guard])) {
                    $permissions[$permission->guard] = [$permission->name];
                } else {
                    $permissions[$permission->guard][] = $permission->name;
                }
            }
            $roles = $this->resource->roles->map(fn($role) => $role->name);

            return [
                'id' => $this->resource->id,
                'name' => $this->resource->name,
                'username' => $this->resource->username,
                'email' => $this->resource->email,
                'groups' => $roles,
                'canImpersonate' => $this->resource->canImpersonate(),
                'permissions' => $permissions,
            ];
        }

        return [
            'id' => 'anonymous',
            'name' => 'anonymous',
            'username' => 'anonymous',
            'email' => 'anonymous',
            'groups' => [],
            'canImpersonate' => false,
            'permissions' => [],
        ];
    }
}
