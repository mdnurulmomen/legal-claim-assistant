<?php

namespace App\Http\Controllers\Api\User\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ManagerSelectResource extends JsonResource
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
            'value' => $this->id,
            'label' => $this->name,
        ];
    }
}
