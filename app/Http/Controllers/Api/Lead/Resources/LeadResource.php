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
            'buyer_name' => $this->buyer_name,
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
