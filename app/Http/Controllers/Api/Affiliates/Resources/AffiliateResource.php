<?php

namespace App\Http\Controllers\Api\Affiliates\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AffiliateResource extends JsonResource
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
            'address' => $this->affiliate?->address,
            'country' => $this->affiliate?->country,
            'total_posting_docs' => $this->posting_docs_count ?? $this->postingDocs->count(),
            'status' => $this->status,
            'affid' => $this->data['affid'] ?? null,
            'created_at' => $this->created_at?->toDateTimeString() ?? null,
            'bank_name' => $this->affiliate?->bank_name,
            'bank_account_name' => $this->affiliate?->bank_account_name,
            'bank_account_number' => $this->affiliate?->bank_account_number,
            'bank_swift_code' => $this->affiliate?->bank_swift_code,
            'vat_number' => $this->affiliate?->vat_number,
        ];
    }
}
