<?php

namespace App\Http\Controllers\Api\Lead\Resources;

use App\Services\LeadService;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        $leads = [
            'id' => $this->id,
            'buyer_integration' => $this->buyer_integration,
            'buyer_name' => $this->buyer_name,
            'affiliate_name' => $this->affiliate_name,
            'lead_status' => $this->lead_status,
            'revenue' => $this->revenue,
            'profit' => $this->profit,
            'affiliate_payout' => $this->affiliate_payout,
            'affiliate_margin' => $this->affiliate_margin,
            'email' => $this->email,
            'phone' => $this->phone
        ];

        $data = array_merge($leads, $this->datas);

        if (empty($request->is_export)){
            return $data;
        }

        return (new LeadService())->filterDataForExport($data);
    }
}
