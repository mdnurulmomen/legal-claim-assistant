<?php

namespace App\Helpers;

class Utility
{
    /**
     * Default user status
     *
     * @var array
     */
    public static $userStatus = [
        1 => 'Active',
        0 => 'Inactive'
    ];

    /**
     * Define user status
     *
     * @var array
     */
    public static $userRoles = [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'user' => 'User',
        'partner' => 'Partner',
        'advertiser' => 'Advertiser',
        'affiliate' => 'Affiliate'
    ];

    /**
     * Define Invoice status
     *
     * @var array
     */
    public static $invoiceStatuses = [
        'Pending' => 'Pending',
        'Rejected' => 'Rejected',
        'Unpaid' => 'Unpaid',
        'Paid' => 'Paid'
    ];

    /**
     * Define Report Tabs
     *
     * @var array
     */
    public static $reportTabs = [
        'list_id' => 'List',
        'affiliate_id' => 'Affiliate',
        'buyer_id' => 'Buyer',
        'buyer_integration_id' => 'Buyer Integration',
        'affid' => 'AffId'
    ];

    /**
     * Define Page Slugs
     *
     * @var array
     */
    public static $pageSlugs = [
        'report' => 'Report',
        'global_leads' => 'Global Leads',
        'platform_leads' => 'Platform Leads'
    ];

    /**
     * Define Log Types
     *
     * @var array
     */
    public static $aliasLogTypes = [
        'lead_export' => 'lead_export',
        'report_export' => 'report_export',
        'integration_trigger' => 'integration_trigger'
    ];

}
