<?php

namespace App\Http\Controllers\Api\Lead\Resources;

use App\Traits\AffiliateTrait;
use Illuminate\Http\Resources\Json\JsonResource;

class ExcelLeadResource extends JsonResource
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
        $columns = json_decode($request->columns, true);

        $leads = [
            'id' => $this->id
        ];

        collect($columns)->each(function($field) use (&$leads) {
            $leads[$field] = $this->{$field};
        });

        return $leads;
    }
}
