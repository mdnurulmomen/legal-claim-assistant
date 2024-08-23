<?php

namespace App\Helpers;

class PlatformHandler
{
    /**
     * Phone number Formats
     *
     * @var array
     */
    public static $numberFormats = [
        'national' => '(XXX) XXX-XXXX',
        'national_raw' => 'XXXXXXXXXX',
        'national_dashed' => 'XXX-XXX-XXXX',
        'national_spaced' => 'XXX XXX XXXX',
        'E164' => '+1XXXXXXXXXX',
        'e164_raw' => '1XXXXXXXXXX'
    ];

    /**
     * Buyer Types
     *
     * @var array
     */
    public static $buyerTypes = [
        'cpl' => 'CPL',
        'cpa' => 'CPA'
    ];

    /**
     * Integration Methods
     *
     * @var array
     */
    public static $integrationMethods = [
        'get' => 'GET',
        'post' => 'POST'
    ];

    /**
     * Cap Durations
     *
     * @var array
     */
    public static $capDurations = [
        'daily' => 'Daily',
        'weekly' => 'Weekly'
    ];
}
