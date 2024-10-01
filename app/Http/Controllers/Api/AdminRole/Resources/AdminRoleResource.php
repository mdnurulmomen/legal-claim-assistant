<?php

namespace App\Http\Controllers\Api\AdminRole\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminRoleResource extends JsonResource
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
            'is_show_affiliate' => (bool)$this->is_show_affiliate
        ];
    }
}
