<?php

namespace App\Http\Controllers\Api\PostbackLog\Resources;

use App\Helpers\SettingHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostbackLogResource extends JsonResource
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
            'request_id' => $this->request_id,
            'lead_id' => $this->lead_id,
            'type' => $this->type,
            'success' => $this->success,
            'data' => $this->data,
            'created_at' => $this->created_at,
        ];
    }
}
