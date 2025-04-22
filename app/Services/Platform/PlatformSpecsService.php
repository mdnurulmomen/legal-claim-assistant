<?php

namespace App\Services\Platform;

use App\Models\EmailTemplate;
use App\Models\PartnerPlatformConnection;
use App\Models\PlatformList;
use App\Models\SpecsChangesNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\Buyer;

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
     * Notify the affiliate user about the specs changes.
     *
     * @param Request $request
     * @param PartnerPlatformConnection $specs
     * @param User $user
     * @return void
     */
    public function notifyAffiliate(Request $request, PartnerPlatformConnection $specs, User $specProfile): void
    {
        // Check if notification is enabled
        if (!config('app.specs_update_notify_enabled')) {
            return;
        }

        $user = User::find($request->affiliate_master_id);
        if(empty($user)) {
            return;
        }

        //find out the events that occurred in this change
        $eventsOccured = [];
        //check if price increased or decreased by comparing the old and new payout
        $oldPayout = $request->payout['model'] == 'dynamic' ? $specs->options['lead_posting']['payout']['percentage'] ?? 0 : $specs->options['lead_posting']['payout']['amount'] ?? 0;
        $newPayout = $request->payout['model'] == 'dynamic' ? $request->payout['percentage'] ?? 0 : $request->payout['amount'] ?? 0;
        if ($newPayout != $oldPayout && !($newPayout == 0 && $oldPayout == 0)) {
            $eventsOccured['price_update'] = [
                'old_value' => $request->payout['model'] == 'dynamic' ? $oldPayout . '%' : "$" . $oldPayout,
                'new_value' => $request->payout['model'] == 'dynamic' ? $newPayout . '%' : "$" . $newPayout,
                'context' => $request->payout['model']
            ];
        }

        // Get active notifications with email templates
        $notifications = SpecsChangesNotification::where('status', 'active')
            ->whereNotNull('email_template_id')
            ->whereIn('name', array_keys($eventsOccured))
            ->get();

        if ($notifications->isEmpty()) {
            return;
        }

        foreach ($notifications as $notification) {

            // Get email template
            $emailTemplate = $notification->emailTemplate;

            if (!$emailTemplate) {
                continue;
            }

            // Get recipient email
            $recipientEmail = config('app.specs_update_notify_sandbox')
                ? config('app.specs_update_notify_sandbox_email')
                : $user->email;

            // Process email content with dynamic parameters
            $emailContent = $emailTemplate->content;
            $emailSubject = $emailTemplate->title;

            // Replace dynamic parameters from notification data
            // if (!empty($notification->data)) {
            //     foreach ($notification->data as $key => $value) {
            //         $emailContent = str_replace('{{'.$key.'}}', $value, $emailContent);
            //         $emailSubject = str_replace('{{'.$key.'}}', $value, $emailSubject);
            //     }
            // }

            // Replace specs-specific parameters
            $platform = PlatformList::find($specs->platform_id);
            $platformName = $platform ? $platform->campaign_name : '';
            $specUrl = config('app.spec_endpoint_domain') . '/posting-instructions/' . $platform->tag . '/' . $specProfile->api_token;
            $affiliatePortalUrl = config('app.affiliate_portal_domain') . '/dashboard/campaign/' . $platform->tag;
            $emailContent = str_replace('{{spec_url}}', $specUrl, $emailContent);
            $buyerIDs = Buyer::select('buyers.id')->join('integrations', 'buyers.id', '=', 'integrations.buyer_id')
                        ->whereIn('integrations.buyer_unique_id', $request->buyers)->get();

            $buyerIDs = count($buyerIDs) > 0 ? implode(', ', array_map(function($id) {
                return '#' . $id;
            }, array_unique($buyerIDs->pluck('buyer_alias_id')->toArray()))) : '';

            //replace template
            $replaceTemplate = [
                'campaign_name' => $platformName,
                'name' => $user->name,
                'email' => $user->email,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'spec_url' => $specUrl,
                'affiliate_portal_url' => $affiliatePortalUrl,
                'old_value' => $eventsOccured['price_update']['old_value'],
                'new_value' => $eventsOccured['price_update']['new_value'],
                'context' => $eventsOccured['price_update']['context'],
                'event' => $notification->name,
                'buyer_ids' => $buyerIDs,
            ];

            //replace dynamic contents
            $emailContent = preg_replace_callback('/{{(.*?)}}/', function ($matches) use ($replaceTemplate) {

                if(array_key_exists($matches[1], $replaceTemplate)){
                    return $replaceTemplate[$matches[1]];
                }

                return '';

            }, $emailContent);

            // Send email
            Mail::send([], [], function ($message) use ($recipientEmail, $emailSubject, $emailContent) {
                $message->to($recipientEmail)
                    ->subject($emailSubject)
                    ->html(json_decode($emailContent, true));
            });
        }
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

        $specs->approve_test_lead = $request->approve_test_lead;
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
