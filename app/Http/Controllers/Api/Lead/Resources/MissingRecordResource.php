<?php

namespace App\Http\Controllers\Api\Lead\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MissingRecordResource extends JsonResource
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
            'id' => $this->id,
        ];

        if(! empty($this->data)) {
            foreach($this->data as $key => $value) {
                $logs[$key] = $value;
            }
        }

        return $logs;
    }
}
