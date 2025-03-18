<?php

namespace App\Http\Controllers\Api\IntegratedMail;

use App\Http\Controllers\Api\IntegratedMail\Requests\CreateOrUpdateIntegratedMailRequest;
use App\Http\Controllers\Controller;
use App\Models\IntegrationEmail;
use App\Services\IntegratedMailService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IntegratedMailController extends Controller
{
    public function saveContent(CreateOrUpdateIntegratedMailRequest $request, IntegratedMailService $service): Response
    {
        $integrationMail = IntegrationEmail::create($request->validated());

        // $integrationMail = IntegrationEmail::latest('id')->first();
        $service->sendIntegrationMail($integrationMail);
        return withSuccess('Integration Mail Created Successfully!');
    }
}
