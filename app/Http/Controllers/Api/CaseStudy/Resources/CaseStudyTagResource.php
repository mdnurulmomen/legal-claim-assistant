<?php

namespace App\Http\Controllers\Api\CaseStudy\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CaseStudyTagResource extends JsonResource
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
            'tag' => $this->tag,
            'name' => $this->name,
            'created_at' => $this->created_at,
        ];
    }
}
