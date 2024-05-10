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

}
