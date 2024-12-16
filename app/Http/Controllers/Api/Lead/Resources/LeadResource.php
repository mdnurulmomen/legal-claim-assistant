<?php

namespace App\Http\Controllers\Api\Lead\Resources;

use App\Models\PlatformList;
use App\Services\Lead\LeadService;
use App\Traits\AffiliateTrait;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    use AffiliateTrait;

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
            'affm_lead_id' => $this->affm_lead_id,
            'buyer_integration_id' => $this->buyer_integration_id,
            'buyer_integration' => $this->buyer_integration,
            'buyer_id' => $this->buyer_id,
            'buyer_name' => $this->buyer_name,
            'affiliate_name' => hasAffiliateAccess() ? $this->affiliate_name : $this->formatAffIds($this->affids),
            'list_name' => $this->list_name,
            'lead_status' => $this->lead_status,
            'revenue' => $this->revenue,
            'profit' => $this->profit,
            'affiliate_payout' => $this->affiliate_payout,
            'affiliate_margin' => $this->affiliate_margin,
            'email' => $this->email,
            'phone' => $this->phone,
            'internal_lead_note' => $this->internal_lead_note,
            'timestamp' => $this->created_at ? $this->created_at->format('Y-m-d H:i') : '',
            'created_at' => $this->created_at,
            'retained_date' => $this->retained_date ? $this->retained_date : '',
            'sold_type' => $this->sold_type,
            "first_name" => '',
            "last_name" => '',
            "attorney" => '',
            "talcum_length" => '',
            "cancer" => '',
            "diagnosed_year" => '',
            "diagnosed_year_old" => '',
            "diagnosed_before_70" => '',
            "description" => '',
            "ip_address" => '',
            "list_id" => $this->list_id,
            "clickId" => '',
            "affid" => '',
            "jornaya_leadid" => '',
            "transaction_id" => '',
            "zip_code" => '',
            "state" => '',
            "user_agent" => '',
            "device" => '',
            "optin_date" => '',
            "unique_id" => '',
            "dp_unique_id" => '',
            "page_source" => '',
            "cd_data" => '',
            "is_valid_state" => '',
            "trusted_form_url" => '',
            "trusted_form_cert_id" => '',
            "city" => '',
            "pageurl" => '',
            "affm_source_id" => '',
            "fb_click_id" => '',
            "utm_content" => '',
            "relation" => '',
            "age_claimant" => '',
        ];

        $data = $this->datas;

        if (array_key_exists('lead_status', $data)) {
            unset($data['lead_status']);
        }

        if (array_key_exists('list_id', $data)) {
            unset($data['list_id']);
        }

        $leads = array_merge($leads, $data);

        // if (! empty($request->is_export)){
        //     return (new LeadService())->filterDataForExport($leads);
        // }

        return $leads;
    }
}
