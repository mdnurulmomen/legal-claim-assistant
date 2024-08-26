<?php

namespace App\Http\Controllers\Api\PlatformListPing\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlatformListPingResource extends JsonResource
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
            'affiliate_id'          => $this->affiliate_id, 
            'name'                  => $this->name, 
            'list_id'               => $this->list_id, 
            'list_name'             => $this->list_name, 
            'list_tag'              => $this->list_tag, 
            'phone'                 => $this->phone, 
            'ping_id'               => $this->ping_id, 
            'buyer'                 => $this->buyer, 
            'internal_buyer_price'  => $this->internal_buyer_price, 
            'affiliate_price'       => $this->affiliate_price, 
            'sold'                  => $this->sold, 
            'accepted'              => $this->accepted, 
            'created_at'            => $this->created_at->toDateTimeString()
        ];
    }
}
