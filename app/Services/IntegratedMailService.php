<?php

namespace App\Services;

use App\Mail\IntegrationMail;
use Illuminate\Support\Facades\Mail;

class IntegratedMailService
{
    public function sendIntegrationMail($mailInfo)
    {
        Mail::to("subhesadek89990@gmail.com")->queue(new IntegrationMail($mailInfo->toArray()));
    }
}
