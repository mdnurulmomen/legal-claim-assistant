<?php

namespace App\Services;

use App\Models\PageSetting;
use App\Models\SavedReport;
use Illuminate\Http\Request;

class SettingService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Formats the request data by adding the 'user_id' key with the authenticated user's ID.
     *
     * @param array $requestData The request data to be formatted.
     * @return array The formatted request data.
     */
    public function formatRequestData(array $requestData): array
    {
        $requestData['user_id'] = auth()->id();
        return $requestData;
    }

    public function saveReportPageSettings(Request $request, SavedReport $savedReport)
    {
        $now = now();

        $pageSettings = PageSetting::where('page', 'report')
                            ->where('user_id', auth()->id())
                            ->get()
                            ->map(function($item) use ($savedReport, $now) {
                                unset($item['id']);
                                $item['uid'] = $savedReport->uid;
                                $item['data'] = json_encode($item['data']);
                                $item['created_at'] = $now;
                                $item['updated_at'] = $now;
                                return $item;
                            })
                            ->toArray();

        PageSetting::insert($pageSettings);
    }
}
