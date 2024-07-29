<?php

namespace App\Http\Controllers\Api\Lead\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'is_retainer' => (bool) $this->is_retainer,
            'is_paid' => (bool) $this->is_paid,
            'is_posted' => (bool) $this->is_posted,
            'is_internal' => (bool) $this->is_internal,
            'lead_revenue' => (float) $this->lead_revenue,
            'affiliate_payout' => (float) $this->affiliate_payout,
            'lead_profit' => (float) $this->lead_profit,
            'affiliate_margin' => (float) $this->affiliate_margin,
            'profit_margin' => (float) $this->profit_margin
        ];
    }
}
