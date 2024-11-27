<?php

namespace App\Http\Controllers\Api\ConferenceEvent\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ConferenceEventResource extends JsonResource
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
            'thumb' => !empty($this->thumb) ? Storage::disk('s3')->url($this->thumb) : '',
            'event_start_date' => $this->event_start_date,
            'event_end_date' => $this->event_end_date,
        ];
    }
}
