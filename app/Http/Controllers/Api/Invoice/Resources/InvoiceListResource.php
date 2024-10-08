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
            // 'invoice_by' => $this->partner->partner?->company ?? $this->partner->name,
            'invoice_by' => '',
            'amount' => minusBeforeDollarSign($this->currency, $this->amount),
            'status' => $this->status,
            // 'listresult' => $this->listresult,
            'listresult' => [],
            'file' => url(Storage::url('invoices/' . $this->file)),
            'created_at' => $this->created_at->toDateTimeString()
        ];
    }
}
