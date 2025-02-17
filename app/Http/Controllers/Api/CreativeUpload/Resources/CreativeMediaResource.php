<?php

namespace App\Http\Controllers\Api\CreativeUpload\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CreativeMediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'tag'                   => $this->tag,
            'creative_upload_id'    => $this->creative_upload_id,
            'file_type'             => $this->file_type,
            'attachment'            => $this->attachment,
            'attachment_url'        => Storage::disk('s3')->url($this->attachment),
            'status'                => $this->status,
            'created_at'            => $this->created_at,
        ];
    }
}
