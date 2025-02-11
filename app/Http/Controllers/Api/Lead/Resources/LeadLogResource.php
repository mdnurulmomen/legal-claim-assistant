<?php

namespace App\Http\Controllers\Api\Lead\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        $logs = [
            'id' => $this->platform_data_id,
            'is_duplicate' => $this->is_duplicate,
            ... $this->data
        ];

        if(! empty($this->updatable_data)) {
            foreach($this->updatable_data as $key => $value) {
                $logs['updatable_'.$key] = $value;
            }
        }

        return $logs;
    }
}
