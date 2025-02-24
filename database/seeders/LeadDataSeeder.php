<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\Affiliate;
use App\Models\Integration;
use App\Models\PlatformData;
use App\Models\PlatformList;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class LeadDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', -1);

        // Pre-load necessary data for lookup
        $listIds = PlatformList::pluck('id')->toArray();
        $affiliateIds = Affiliate::pluck('id')->toArray();
        $integrations = Integration::select('id', 'buyer_id')->get()
                                    ->mapWithKeys(fn($item) => [(string) $item['id'] => (string) $item['buyer_id']])
                                    ->toArray();
        $leadStatuses = ['Pending', 'Returned', 'Disqualified', 'Sent Agreement', 'Agreement Signed', 'Retained'];
        $now = now();

        // Initialize Faker instance
        $faker = Faker::create();

        // Set the number of records you want to insert
        $totalRecords = 1000000;
        $batchSize = 1000;  // Number of records per batch

        // for ($i = 0; $i < $totalRecords; $i += $batchSize) {
        //     $leads = [];

        //     for ($j = 0; $j < $batchSize; $j++) {
        //         $lead = $this->getLeadData($listIds, $affiliateIds, array_keys($integrations), $integrations, $leadStatuses, $now, $faker);
        //         $leads[] = $lead;
        //     }

        //     PlatformData::insert($leads);
        // }
    }

    /**
     * Generate a single lead's data.
     */
    public function getLeadData($listIds, $affiliateIds, $integrationIds, $integrations, $leadStatuses, $now, $faker): array
    {
        $phone = '+1' . mt_rand(1000000000, 9999999999);
        $integrationId = $faker->randomElement($integrationIds);

        // Lead data array
        $data = [
            'affid' => $faker->regexify('[A-Z]{5}[0-4]{3}'),
            'injury' => $faker->words(3, true),
            'attorney' => $faker->words(3, true),
            'last_name' => $faker->firstName,
            'first_name' => $faker->lastName,
            'ip_address' => $faker->ipv4,
            'lead_buyer' => $faker->words(3, true),
            'optin_date' => $faker->date('Y-m-d'),
            'user_agent' => $faker->words(3, true),
            'page_source' => $faker->words(3, true),
            'camp_lejeune' => $faker->words(3, true),
            'landing_page' => $faker->words(3, true),
            'jornaya_leadid' => $faker->regexify('[A-Z]{5}[0-4]{3}'),
            'tortexpert_msg' => $faker->words(3, true),
            'tortexpert_error' => $faker->words(3, true),
            'tortexpert_price' => $faker->randomFloat(2, 1, 1000),
            'trusted_form_url' => $faker->url,
            'trusted_form_cert_id' => $faker->words(3, true),
            'phone_digit_formatted' => $faker->randomNumber(5)
        ];

        // Main lead data
        $lead = [
            'affm_lead_id' => $faker->uuid,
            'list_id' => $faker->randomElement($listIds),
            'affiliate_id' => $faker->randomElement($affiliateIds),
            'affid' => $faker->regexify('[A-Z]{5}[0-4]{3}'),
            'phone' => $phone,
            'email' => $faker->safeEmail(),
            'buyer_integration_id' => $integrationId,
            'buyer_id' => $integrations[$integrationId] ?? null,
            'lead_status' => $faker->randomElement($leadStatuses),
            'affm_source_id' => $faker->regexify('[A-Z]{5}[0-4]{3}'),
            'sold_type' => $faker->randomElement(['CPL', 'CPA']),
            'internal_lead_note' => $faker->paragraph(2),
            'datas' => json_encode($data),
            'revenue' => $faker->randomNumber(5, true),
            'payout' => $faker->randomNumber(5, true),
            'is_retainer' => $faker->randomElement([0, 1]),
            'is_sold' => $faker->randomElement([0, 1]),
            'is_internal' => $faker->randomElement([0, 1]),
            'retained_date' => $faker->dateTime()->format('Y-m-d H:i:s'),
            'created_at' => $now,
            'updated_at' => $now
        ];

        return $lead;
    }
}
