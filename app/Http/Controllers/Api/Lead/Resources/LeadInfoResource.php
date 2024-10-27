<?php

namespace App\Http\Controllers\Api\Lead\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadInfoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        $datas = $this->datas;

        $leads = [
            'id' => $this->id,
            'buyer_integration' => $this->buyer_integration,
            'buyer_name' => $this->buyer_name,
            'affiliate_name' => hasAffiliateAccess() ? $this->affiliate_name : '',
            'email' => $this->email,
            'phone' => $this->phone,
            'revenue' => $this->revenue,
            'profit' => $this->profit,
            'affiliate_payout' => $this->affiliate_payout,
            'affiliate_margin' => $this->affiliate_margin,
        ];

        if(! empty($request->is_all)){
            $leads = array_merge($leads, [
                'buyer_id' => $this->buyer_id,
                'buyer_integration_id' => $this->buyer_integration_id,
                'list_id' => $this->list_id,
                'affiliate_id' => $this->affiliate_id,
                'affid' => $this->affid,
                'lead_status' => $this->lead_status,
                'affm_source_id' => $this->affm_source_id,
                'affiliate_specs_id' => $this->affiliate_specs_id,
                'sold_type' => $this->sold_type,
                'is_internal'      => $this->is_internal,
            ]);
        }

        if(array_key_exists('list_id', $this->datas)){
            unset($datas['list_id']);
        }

        return array_merge($leads, $datas);
    }
}
