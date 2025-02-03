<?php

namespace App\Http\Controllers\Api\Test\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestResource extends JsonResource
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
            'name' => $this->data['name'] ?? null,
            'loggable_id' => $this->loggable_id,
            'loggable_type' => $this->loggable_type,
            'cv_trigger' => $this->data['cv_trigger'] ?? null,
            'integration' => $this->data['integration'] ?? null
        ];
    }
}
