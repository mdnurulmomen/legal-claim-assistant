<?php

namespace App\Http\Controllers\Api\ConferenceEvent\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'title' => $this->title,
            'description' => $this->description,
            'thumb' => $this->thumb,
            'event_start_date' => $this->event_start_date,
            'event_end_date' => $this->event_end_date,
            'event_button_text' => $this->event_button_text,
            'event_link' => $this->event_link,
        ];
    }
}
