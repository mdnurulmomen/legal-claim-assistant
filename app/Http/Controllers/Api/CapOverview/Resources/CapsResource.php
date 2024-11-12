<?php

namespace App\Http\Controllers\Api\CapOverview\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CapsResource extends JsonResource
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
            'list_id' => $this->list_id,
            'integration_id' => $this->integration_id,
            'buyer_id' => $this->buyer_id,
            'list_name' => $this->list_name,
            'integration_name' => $this->integration_name,
            'buyer_name' => $this->buyer_name,
            'column_scope' => $this->column_scope,
            'capacity' => $this->capacity,
        ];
    }
}
