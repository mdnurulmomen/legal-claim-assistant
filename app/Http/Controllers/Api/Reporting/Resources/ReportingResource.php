<?php

namespace App\Http\Controllers\Api\Reporting\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportingResource extends JsonResource
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
            'platform_name' => $this->platform_name,
            'revenue' => $this->revenue,
            'profit' => $this->profit,
            'affiliate_payout' => $this->affiliate_payout,
            'revenue_per_lead' => number_format($this->revenue_per_lead, 2),
            'average_profit' => number_format($this->average_profit, 2),
            'affiliate_average_payout' => number_format($this->affiliate_average_payout, 2),
        ];
    }
}
