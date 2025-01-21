<?php

namespace App\Http\Controllers\Api\CaseStudy\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CaseStudyResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'attachment' => !empty($this->attachment) ? Storage::disk('s3')->url($this->attachment) : '',
        ];
    }
}
