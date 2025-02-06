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
        $data =  [
            'id' => $this->id,
            'tag' => $this->tag,
            'title' => $this->title,
            'auth_name' => $this->auth_name,
            'status' => $this->status,
            'categories' => $this->categories,
            'tags' => $this->tags,
            'description' => $this->description,
            'attachment' => $this->attachment,
            'created_at' => $this->created_at,
        ];

        if( !empty($this->attachment) ){
            $data['attachment_urls'] = [
                'thumb' => Storage::disk('s3')->url($this->attachment['thumb']),
                'original' => Storage::disk('s3')->url($this->attachment['original'])
            ];
        } else {
            $data['attachment_urls'] = [];
        }

        return $data;
    }
}
