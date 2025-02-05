<?php

namespace App\Http\Controllers\Api\PortalOffers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PortalOffersResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tag' => $this->tag,
            'name' => $this->name,
            'description' => $this->description,
            'active' => $this->active,
            'img_path' => $this->img,
            'img' => $this->img ? Storage::disk('s3')->url($this->img) : null,
            'payout_range_cpl_min' => $this->payout_range_cpl_min,
            'payout_range_cpl_max' => $this->payout_range_cpl_max,
            'payout_range_cpa_min' => $this->payout_range_cpa_min,
            'payout_range_cpa_max' => $this->payout_range_cpa_max,
            'preview_link' => $this->preview_link,
            'criteria' => $this->criteria,
            'created_at' => $this->created_at,
        ];
    }
} 