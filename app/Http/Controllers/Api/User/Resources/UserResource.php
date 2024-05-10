<?php

namespace App\Http\Controllers\Api\User\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'logo' => $this->logo,
            'phone' => $this->phone,
            'workspace' => $this->workspace,
            'role' => $this->role,
            'admin_role_id' => $this->admin_role_id,
            'admin_role_name' => $this->adminRole ? $this->adminRole->name : null,
            'status' => $this->status
        ];
    }
}
