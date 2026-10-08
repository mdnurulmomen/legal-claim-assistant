<?php

namespace App\Helpers;


class SettingHandler
{
    /**
     * List of postBack events
     *
     * @var array
     */
    public static $postBackEvents = [
        'on_retainer_added' => 'On Retainer Added',
        // 'on_lead_update' => 'On Lead Update',
        'on_retainer_revenue_update' => 'On Retainer Revenue Update'
    ];
}
