<?php

namespace App\Http\Controllers\Api\CaseStudy\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CaseStudyCategoryResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'tag' => $this->tag,
            'name' => $this->name,
            'parent_name' => $this->parent?->name,
            'created_at' => $this->created_at,
        ];
    }
}
