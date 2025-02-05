<?php

namespace App\Http\Controllers\Api\PlatformList\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationResource extends JsonResource
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
            'name' => $this->name,
            'buyer_unique_id' => $this->buyer_unique_id,
            'buyer_id' => $this->buyer_id,
            'list_id' => $this->list_id,
            'buyer_headers' => $this->buyer_headers,
            'type' => $this->type,
            'lead_id_key' => $this->lead_id_key,
            'note' => $this->note
        ];
    }
}
