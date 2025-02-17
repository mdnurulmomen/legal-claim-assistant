<?php

namespace App\Http\Controllers\Api\CreativeUpload\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Api\CreativeUpload\Resources\CreativeMediaResource;
use App\Http\Controllers\Api\CreativeTemplate\Resources\CreativeTemplateResource;
use App\Http\Controllers\Api\CreativeTemplate\Resources\TemplateOfferResource;

class CreativeUploadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $data = [
            'id'                    => $this->id,
            'tag'                   => $this->tag,
            'type'                   => $this->type,
            'name'                  => $this->name,
            'affiliate_name'        => $this->user->name,
            'template_offer_id'     => $this->template_offer_id,
            'creative_template_id'  => $this->creative_template_id,
            'offer'                 => $this->offer ? new TemplateOfferResource($this->offer) : null,
            'template'              => $this->template ? new CreativeTemplateResource($this->template) : null,
            'description'           => $this->description,
            'attachments_paths'   => $this->attachments ?? [],
            'attachments'   => array_map(function($item){
                return Storage::disk('s3')->url($item);
            }, !empty($this->attachments) ? $this->attachments : []),
            'creative_attachments'   => $this->creative_attachments ?  CreativeMediaResource::collection($this->creative_attachments) : null,
            'status'        => $this->status,
            'created_at'    => $this->created_at->format('d M Y'),
        ];

        if($this->offer && $this->offer->templates){
            $data['templates'] = CreativeTemplateResource::collection($this->offer->templates);
        }
        return $data;
    }
}
