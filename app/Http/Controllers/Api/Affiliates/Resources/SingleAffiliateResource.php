<?php

namespace App\Http\Controllers\Api\Affiliates\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SingleAffiliateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'logo' => $this->logo,
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company_name' => $this->affiliate?->company_name,
            'country' => $this->affiliate?->country,
            'address' => $this->affiliate?->address,
            'zip' => $this->affiliate?->zip,
            'bank_name' => $this->affiliate?->bank_name,
            'bank_account_name' => $this->affiliate?->bank_account_name,
            'bank_account_number' => $this->affiliate?->bank_account_number,
            'bank_swift_code' => $this->affiliate?->bank_swift_code,
            'vat_number' => $this->affiliate?->vat_number,
            'status' => $this->status,
            'affid' => $this->data['affid'] ?? null,
//            'workspace' => $this->workspace,
            'is_test' => $this->is_test,
            'total_posting_docs' => $this->postingDocs->count(),
        ];
    }
}
