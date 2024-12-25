<?php

namespace App\Http\Controllers\Api\CreativeTemplate\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CreativeTemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'tag'               => $this->tag,
            'name'              => $this->name,
            'offer_name'        => $this->offer->name ?? null,
            'template_offer_id' => $this->template_offer_id,
            'attachments' => !empty($this->attachments) ? Storage::disk('s3')->url($this->attachments) : '',
            'attachments_path' => $this->attachments,
            'created_at'        => $this->created_at,
        ];
    }
}
