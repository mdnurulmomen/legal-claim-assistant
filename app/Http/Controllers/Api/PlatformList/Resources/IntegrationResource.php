<?php

namespace App\Http\Controllers\Api\PlatformList\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        // {
        //     "auth": false,
        //     "caps": [
        //       {
        //         "active": true,
        //         "amount": "500",
        //         "column": {
        //           "affid": "10"
        //         },
        //         "duration": "daily"
        //       }
        //     ],
        //     "curl": {
        //       "url": "http://localhost/api/v1/geterrr",
        //       "method": "get"
        //     },
        //     "maps": {
        //       "affid": "lp_s1",
        //       "email": "email_address",
        //       "phone": "phone_home",
        //       "injury": "injury",
        //       "attorney": "attorney",
        //       "last_name": "last_name",
        //       "first_name": "first_name",
        //       "ip_address": "ip_address",
        //       "description": "description",
        //       "relationship": "relationship",
        //       "jornaya_leadid": "jornaya_lead_id",
        //       "trusted_form_url": "trusted_form_cert_id"
        //     },
        //     "name": "tortexpert",
        //     "alias": "Buyer 1",
        //     "order": 1,
        //     "active": true,
        //     "filter": {
        //       "injury": [
        //         "!bladder cancer"
        //       ]
        //     },
        //     "payout": {
        //       "model": "fixed",
        //       "amount": "0",
        //       "params": "price"
        //     },
        //     "save_data": [
        //       "price",
        //       "status"
        //     ],
        //     "buyer_type": "CPA",
        //     "custom_maps": {
        //       "pageurl": "https://legalclaimassistant.com",
        //       "camp_lejeune": "1953-1987"
        //     },
        //     "convert_maps": {
        //       "lp_s1": {
        //         "16": "116",
        //         "690": "116",
        //         "5277": "102",
        //         "8534": "103",
        //         "8660": "104",
        //         "8712": "101",
        //         "9505": "105",
        //         "51820": "201",
        //         "271899": "201",
        //         "271962": "202",
        //         "704159": "602",
        //         "704318": "601"
        //       },
        //       "injury": {
        //         "Liver Cancer": "Liver cancer",
        //         "Kidney Cancer": "Kidney cancer",
        //         "Adult Leukemia": "Leukemia",
        //         "Bladder Cancer": "Bladder cancer",
        //         "Renal Toxicity": "Renal toxicity",
        //         "Multiple Myeloma": "Multiple myeloma",
        //         "Renal/Kidney Failure": "Kidney disease",
        //         "Parkinson’s Disease": "Parkinson's disease",
        //         "Non-Hodgkin’s Lymphoma": "Non-Hodgkin's lymphoma",
        //         "Myelodysplastic Syndromes": "MDS (Myelodysplastic syndromes)",
        //         "Kidney Damage (Must be on dialysis)": "Kidney disease",
        //         "Kidney Disease (end stage renal disease)": "Kidney disease",
        //         "Aplastic Anemia and other Myelodysplastic Syndromes": "Aplastic anemia"
        //       }
        //     },
        //     "phone_format": "national",
        //     "buyer_profile": "22",
        //     "internal_buyer": true,
        //     "buyer_payout_by_affid": [
        //       {
        //         "affid": 12,
        //         "logic": "fixed",
        //         "value": "500"
        //       }
        //     ]
        //   }
        return [
            'auth' => $this->test,
            // 'caps' => $this->caps,
            // 'curl' => $this->curl,
            // 'maps' => $this->maps,
            // 'name' => $this->name,
            // 'alias' => $this->alias,
            // 'order' => $this->order,
            // 'active' => $this->active,
            // 'filter' => $this->filter,
            // 'payout' => $this->payout,
            // 'save_data' => $this->save_data,
            // 'buyer_type' => $this->buyer_type,
            // 'custom_maps' => $this->custom_maps,
            // 'convert_maps' => $this->convert_maps,
            // 'phone_format' => $this->phone_format,
            // 'buyer_profile' => $this->buyer_profile,
            // 'internal_buyer' => $this->internal_buyer,
            // 'buyer_payout_by_affid' => $this->buyer_payout_by_affid
        ];
    }
}
