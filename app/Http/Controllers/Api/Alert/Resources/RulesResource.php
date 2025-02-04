<?php

namespace App\Http\Controllers\Api\Alert\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RulesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'rules' => $this['rules'],
            'period' => $this['check_period'],
            'all_possible_receivers' => $this['all_possible_receivers'],
            'all_possible_lists' => $this['all_possible_lists'],
        ];
    }
}
