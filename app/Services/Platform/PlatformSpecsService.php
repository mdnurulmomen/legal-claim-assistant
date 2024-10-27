<?php

namespace App\Services\Platform;

use App\Models\PartnerPlatformConnection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlatformSpecsService
{

    /**
     * Save an affiliate user.
     *
     * @param Request $request
     * @param User $user
     * @return void
     */
    public function saveAffiliate(Request $request, User $user): void
    {
        $user->master_user_id = $request->affiliate_master_id;
        $user->name = $request->name;
        $user->email = $request->email;
        $user->save();
    }

    /**
     * Save the specs for the given platform.
     *
     * @param Request $request
     * @param PartnerPlatformConnection $specs
     * @return void
     */
    public function saveSpecs(Request $request, PartnerPlatformConnection $specs): void
    {
        $options = $specs->options;
        $options['posting_type'] = $request->posting_type;
        $options['force_pingpost_sell'] = (int) $request->force_pingpost_sell;
        $options['internal_affiliate'] = (bool) $request->internal_affiliate;
        $options['affid'] = $request->affid;

        if(! array_key_exists('lead_posting', $options)){
            $options['lead_posting'] = [];
        }

        $options['lead_posting']['ping_required_fields'] = $request->ping_required_fields;
        $options['lead_posting']['payout'] = $request->payout;
        $options['lead_posting']['buyers'] = $request->buyers;
        $options['lead_posting']['optional_fields'] = $request->optional_fields;
        $options['lead_posting']['required_fields'] = $request->required_fields;

        $specs->options = $options;
        $specs->save();
    }

    /**
     * Create a new affiliate user.
     *
     * @param Request $request
     * @return User
     */
    public function createAffiliateUser(Request $request): User
    {
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->username = $this->generateUserName();
        $user->role = 'advertiser';
        $user->password = '123456';
        $user->master_user_id = $request->affiliate_master_id;
        $user->save();

        return $user;
    }

    /**
     * Generates a random username which is not already in use by another user.
     *
     * @return string
     */
    public function generateUserName(): string
    {
        $username = Str::lower(Str::random(8));

        $totalUsers = User::where('username', $username)->count();

        if ($totalUsers > 0) {
            $username .= $totalUsers;
        }

        return $username;
    }

    /**
     * Create a new specs for the given affiliate user and platform.
     *
     * @param Request $request
     * @param User $user
     * @return PartnerPlatformConnection
     */
    public function createSpecs(Request $request, User $user): PartnerPlatformConnection
    {
        $specs = new PartnerPlatformConnection();
        $specs->user_id = $user->id;
        $specs->platform_id = $request->platform_id;

        $options = [];
        $options['posting_type'] = $request->posting_type;
        $options['force_pingpost_sell'] = (int) $request->force_pingpost_sell;
        $options['internal_affiliate'] = (bool) $request->internal_affiliate;
        $options['affid'] = $request->affid;

        $options['lead_posting'] = [];
        $options['lead_posting']['ping_required_fields'] = $request->ping_required_fields;
        $options['lead_posting']['payout'] = $request->payout;
        $options['lead_posting']['buyers'] = $request->buyers;
        $options['lead_posting']['optional_fields'] = $request->optional_fields;
        $options['lead_posting']['required_fields'] = $request->required_fields;

        $specs->options = $options;
        $specs->save();

        return $specs;
    }
}
