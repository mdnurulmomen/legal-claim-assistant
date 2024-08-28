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
        $data = [
            'id' => $this->id,
            'tag' => $this->tag,
            'name' => $this->name,
            'campaign_name' => $this->campaign_name,
            'total_leads' => number_format($this->total),
            'sources' => array_unique(array_merge([$this->source] ?? [], $this->options['additional_sources'] ?? [])),
            'status' => $this->status,
            'cv_trigger' => $this->cv_trigger ?? [],
            'integrations' => $this->integrations,
            'lead_headers' => $this->lead_headers ?? [],
            'updated_at' => $this->updated_at ? $this->updated_at->diffForHumans() : '',
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : ''
        ];

        return $data;
    }
}
