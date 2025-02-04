<?php

namespace App\Http\Controllers\Api\Alert\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'via' => $this['via'],
            'status' => $this['status'],
            'check_period' => $this['check_period'],
            'check_interval' => $this['check_interval'],
        ];
    }
}
