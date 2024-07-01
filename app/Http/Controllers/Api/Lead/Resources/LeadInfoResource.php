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
        $leads = [
            'id' => $this->id,
            'buyer_name' => $this->buyer_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'revenue' => $this->revenue,
            'payout' => $this->payout
        ];

        return array_merge($leads, $this->datas);
    }
}
