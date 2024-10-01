<?php

namespace App\Http\Controllers\Api\GlobalPostback\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SingleGlobalPostbackResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url, 
            'conditions' => $this->conditions, 
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
