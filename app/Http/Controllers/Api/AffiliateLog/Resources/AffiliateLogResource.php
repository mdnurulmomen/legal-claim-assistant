<?php

namespace App\Http\Controllers\Api\AffiliateLog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AffiliateLogResource extends JsonResource
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
            'affiliate_id' => $this->affiliate_id,
            'action' => $this->action,
            'name' => $this->user->name ?? 'Unknown', 
            'accessId' => $this->accessId, 
            'data' => $this->data,
            'created_at' => $this->created_at,
        ];
    }
}
