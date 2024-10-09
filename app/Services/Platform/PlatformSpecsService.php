<?php

namespace App\Services\Platform;

use App\Models\PartnerPlatformConnection;
use App\Models\User;
use Illuminate\Http\Request;

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
}
