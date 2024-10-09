<?php

namespace App\Http\Controllers\Api\PlatformList\Resources;

use App\Traits\AffiliateTrait;
use Illuminate\Http\Resources\Json\JsonResource;
use stdClass;
use Illuminate\Support\Str;

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
            'email' => $this->email,
            'api_token' => $this->api_token,
            'affiliate_id' => $this->affiliate_id,
            'affiliate_name' => hasAffiliateAccess() ? $this->affiliate_name : $affids,
            'affiliate_master_id' => $this->affiliate_master_id,
            'affids' => $affids,
            'affid' => $this->affid,
            'label' => $this->label ? Str::title(Str::replace('_', ' ', $this->label)) : '',
            'posting_type' => $this->label,
            'platform_tag' => $this->platform_tag,
            'is_active' => $this->is_active,
            'buyers_count' => $this->buyers_count,
            'ping_required_fields' => $this->ping_required_fields ? json_decode($this->ping_required_fields) : [],
            'buyers' => $this->buyers ? json_decode($this->buyers) : [],
            'payout' => $this->payout ? json_decode($this->payout) : new stdClass(),
            'optional_fields' => is_string($this->optional_fields) ? json_decode($this->optional_fields) : $this->optional_fields,
            'required_fields' => $this->required_fields ? json_decode($this->required_fields) : [],
            'force_pingpost_sell' => (bool)((int) $this->force_pingpost_sell),
            'created_at' => $this->created_at ? $this->created_at->setTimezone($timezone)->format('Y-m-d h:i:s a') : ''
        ];
    }
}
