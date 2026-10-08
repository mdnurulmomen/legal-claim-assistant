<?php

namespace App\Http\Controllers\Api\Alert\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LogsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'logs' => $this['data'],
            'total' => $this['total'],
            'per_page' => $this['per_page'],
            'current_page' => $this['current_page'],
            'last_page' => $this['last_page'],   
            'status' => $this['status']
        ];
    }
}
