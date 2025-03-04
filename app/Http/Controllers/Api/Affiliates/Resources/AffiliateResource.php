<?php

namespace App\Http\Controllers\Api\Affiliates\Resources;

use App\Traits\AffiliateTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AffiliateResource extends JsonResource
{
    use AffiliateTrait;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $affids = ! empty($this->data['affids']) ? $this->formatAffIds((json_encode($this->data['affids']))) : '';

        $data = [
            'id' => $this->id,
            'logo' => $this->logo,
            'username' => $this->username,
            'affids' => $affids,
            'name' => $this->name,
            'email' => '',
            'phone' => '',
            'total_posting_docs' => $this->posting_docs_count,
            'status' => $this->status,
            'affid' => $this->data['affid'] ?? null,
            'created_at' => $this->created_at?->toDateTimeString() ?? null,
            'company_name' => '',
            'address' => '',
            'country' => '',
            'bank_name' => '',
            'bank_account_name' => '',
            'bank_account_number' => '',
            'bank_swift_code' => '',
            'vat_number' => '',
            'data' => $this->data ? (object) $this->data : new \stdClass
        ];

        $hasAccess = hasAffiliateAccess();

        if ($hasAccess && $this->affiliate) {
            $data['company_name'] = $this->affiliate->company_name;
            $data['address'] = $this->affiliate->address;
            $data['country'] = $this->affiliate->country;
            $data['bank_name'] = $this->affiliate->bank_name;
            $data['bank_account_name'] = $this->affiliate->bank_account_name;
            $data['bank_account_number'] = $this->affiliate->bank_account_number;
            $data['bank_swift_code'] = $this->affiliate->bank_swift_code;
            $data['vat_number'] = $this->affiliate->vat_number;
        }

        if($hasAccess){
            $data['email'] = $this->email;
            $data['phone'] = $this->phone;
        }

        if(! $hasAccess){
            $data['name'] = $affids;
        }

        return $data;
    }
}
