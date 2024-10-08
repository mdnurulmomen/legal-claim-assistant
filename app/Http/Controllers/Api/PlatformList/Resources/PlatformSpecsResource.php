<?php

namespace App\Http\Controllers\Api\PlatformList\Resources;

use App\Traits\AffiliateTrait;
use Illuminate\Http\Resources\Json\JsonResource;
use stdClass;

class PlatformSpecsResource extends JsonResource
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
        $timezone = empty($request->timezone) ? 'Europe/Berlin' : $request->timezone;
        $affids = $this->formatAffIds($this->affids);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'api_token' => $this->api_token,
            'affiliate_name' => hasAffiliateAccess() ? $this->affiliate_name : $affids,
            'affids' => $affids,
            'label' => $this->label,
            'platform_tag' => $this->platform_tag,
            'is_active' => $this->is_active,
            'buyers_count' => $this->buyers_count,
            'buyers' => $this->buyers ? json_decode($this->buyers) : [],
            'payout' => $this->payout ? json_decode($this->payout) : new stdClass(),
            'created_at' => $this->created_at ? $this->created_at->setTimezone($timezone)->format('Y-m-d h:i:s a') : ''
        ];
    }
}
