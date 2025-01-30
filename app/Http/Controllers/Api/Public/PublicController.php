<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\Public\Requests\LeadDetailsRequest;
use App\Http\Controllers\Controller;
use App\Models\PlatformData;
use App\Services\Public\PublicService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicController extends Controller
{
    /**
     * Returns a lead data based on the provided phone and email.
     *
     * @param LeadDetailsRequest $request
     * @param PublicService $publicService
     * @return Response
     */
    public function leadDetails(LeadDetailsRequest $request, PublicService $publicService): Response
    {
        if($request->header('X-PLATFORM-KEY') !== config('app.platform_key')) {
            return withError('Invalid key provided!');
        }

        $lead = PlatformData::query()
                ->where([
                    'platform_datas.phone' => $request->phone,
                    'platform_datas.email' => $request->email
                ])
                ->leftJoin('platform_lists as pl', 'platform_datas.list_id', '=', 'pl.id')
                ->latest('platform_datas.id')
                ->select([
                    'platform_datas.*',
                    'pl.lead_headers',
                    'pl.name as list_name'
                ])
                ->first();

        if(empty($lead)) {
            return withError('Invalid credentials has been provided!');
        }

        $formattedLead = $publicService->formatLead($lead->toArray());
        return withSuccess($formattedLead);
    }

}
