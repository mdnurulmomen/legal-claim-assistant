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

        $avg_retain_time = "-";

        if ($this->accepted !== null || $this->retained == 0) {
            $dayLabel = ($this->accepted > 1) ? "Days" : "Day";
            $avg_retain_time = number_format($this->avg_retain_time) . " " . $dayLabel;
        }

        return [
            'platform_name' => $this->platform_name ?? '',
            'buyer_name' => $this->buyer_name ?? '',
            'integration_name' => $this->integration_name ?? '',
            'affiliate_name' => $this->affiliate_name ?? '',
            'affid' => $this->affid ?? '',
            'posted' => (float) $this->posted,
            'accepted' => (float) $this->accepted,
            'rejected' => (float) $this->rejected,
            'accepted_cpl' => (float) $this->accepted_cpl,
            'retained' => (float) $this->retained,
            'avg_retained_leads' => $this->accepted > 0 ? number_format($this->retained / $this->accepted * 100, 2) . "%" : "0.00%",
            'avg_retain_time' => $avg_retain_time,
            'acceptance_rate' => (float) $this->acceptance_rate,
            'acceptance_rate_cpl' => (float) $this->acceptance_rate,
            'revenue' => (float) $this->revenue,
            'profit' => (float) $this->profit,
            'affiliate_payout' => (float) $this->affiliate_payout,
            'revenue_per_lead' => (float) $this->revenue_per_lead,
            'average_profit' => (float) $this->average_profit,
            'affiliate_average_payout' => (float) $this->affiliate_average_payout
        ];
    }
}
