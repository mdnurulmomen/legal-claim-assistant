<?php

namespace App\Http\Controllers\Api\Invoice\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class InvoiceListResource extends JsonResource
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
            'id' => $this->id,
            'tag' => $this->tag,
            'name' => $this->name,
            'monthly_net' => 'Monthly Net +'.$this->monthly_net,
            'invoice_by' => $this->partner->partner?->company ?? $this->partner->name,
            'amount' => minusBeforeDollarSign($this->currency,number_format($this->amount ?? 0, 2, '.', ',')),
            'status' => $this->status,
            'listresult' => $this->listresult,
            'file' => url(Storage::url($this->file)),
            'created_at' => $this->created_at->toDateTimeString()
        ];
    }
}
