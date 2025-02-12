<?php

namespace App\Http\Controllers\Api\PortalOffers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'offer' => new PortalOffersResource($this->whenLoaded('offer')),
            'affiliate' => $this->whenLoaded('affiliate'),
            'notes' => $this->notes,
            'action_notes' => $this->action_notes,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 