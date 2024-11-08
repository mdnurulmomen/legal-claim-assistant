<?php

namespace App\Services\Platform;

use App\Models\PlatformList;
use Illuminate\Http\Request;

class PlatformSettingService
{
    /**
     * Save the platform data.
     *
     * @param Request $request
     * @param PlatformList $platform
     * @return void
     */
    public function savePlatformData(Request $request, PlatformList $platform): void
    {
        $platform->name = $request->name;
        $platform->campaign_name = $request->campaign_name;
        $platform->lead_headers = $request->lead_headers;
        $platform->save();
    }

    /**
     * Save the options for the given platform.
     *
     * @param Request $request
     * @param PlatformList $platform
     * @return void
     */
    public function saveSettingOptions(Request $request, PlatformList $platform): void
    {
        $options = $platform->options;
        $options['additional_source'] = $request->additional_source;
        $options['skip_duplicate'] = (bool) $request->skip_duplicate;

        if(! array_key_exists('gohighlevel', $options)){
            $options['gohighlevel'] = [];
        }

        $options['gohighlevel']['active'] = (bool) $request->is_high_level;
        $options['lead_distribution'] = $request->lead_distribution;
        $options['min_ping_price'] = (float) $request->min_ping_price;
        $options['min_affiliate_ping_prices'] = $request->min_affiliate_ping_prices ?? [];
        $options['global_postback'] = $request->global_postback ?? [];
        $options['dynamic_margin'] = $request->dynamic_margin ?? [];
        $options['buyer_revshare'] = $request->buyer_revshare ?? [];
        $options['lead_posting'] = $request->lead_posting ?? [];
        $options['hidden_values'] = $request->hidden_values ?? [];

        $platform->options = $options;
        $platform->save();
    }

}
