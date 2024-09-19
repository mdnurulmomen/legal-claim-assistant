<?php

namespace App\Http\Controllers\Api\Invoice\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class InvoiceResource extends JsonResource
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
            'invoice_by' => $this->affiliateInfo?->company_name ?? $this->partner->name,
            'amount' => minusBeforeDollarSign($this->currency, $this->amount),
            'status' => $this->status,
            // 'listresult' => $this->listresult,
            //  'file' => url(Storage::url($this->file)),
            'file' => downloadInvoiceFile("https://portal-api.legalclaimassistant.support/invoices/" . $this->file),
            'dueDate' => $this->dueDate,
            'periodDateFrom' => $this->periodDateFrom,
            'periodDateTo' => $this->periodDateTo,
            'description' => $this->description,
            'created_at' => $this->created_at->toDateTimeString()
        ];
    }
}
