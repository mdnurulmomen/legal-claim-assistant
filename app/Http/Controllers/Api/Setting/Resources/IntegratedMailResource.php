<?php

namespace App\Http\Controllers\Api\Setting\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class IntegratedMailResource extends JsonResource
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
            'status' => $this->status,
            'content' => $this->content,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : '',
        ];
    }
}
