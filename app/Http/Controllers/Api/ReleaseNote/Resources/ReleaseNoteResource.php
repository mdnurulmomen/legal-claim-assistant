<?php

namespace App\Http\Controllers\Api\ReleaseNote\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReleaseNoteResource extends JsonResource
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
            'title' => $this->title,
            'version' => $this->version,
            'tages' => $this->tages, 
            'description' => $this->description, 
            'created_at' => $this->created_at->toDateTimeString()
        ];
    }
}
