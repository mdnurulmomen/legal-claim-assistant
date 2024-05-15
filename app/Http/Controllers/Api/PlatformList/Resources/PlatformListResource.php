<?php

namespace App\Http\Controllers\Api\PlatformList\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlatformListResource extends JsonResource
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
            'tag' => $this->tag,
            'name' => $this->name,
            'campaign_name' => $this->campaign_name,
            'total_leads' => number_format($this->total),
            'source' => $this->source,
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString()
        ];
    }
}
