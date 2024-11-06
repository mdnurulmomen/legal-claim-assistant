<?php

namespace App\Http\Controllers\Api\Auth\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AuthResource extends JsonResource
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
            'logo' => $this->logo ? url(Storage::url($this->logo)) : null,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'workspace' => $this->workspace,
            'api_token' => $this->access_token,
            'role' => $this->admin_role,
            'status' => $this->status
        ];
    }
}
