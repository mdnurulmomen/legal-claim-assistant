<?php

namespace App\Services\Alert;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingsService
{
    protected $column = 'platform_lists';
    protected $table = 'site_settings';

    public function __construct($column = 'platform_lists')
    {
        if ($column) {
            $this->column = $column;
        }
    }

    // Method to fetch the platform lists settings from the database
    public function fetchAlertSysSettings()
    {
        $siteSettings = DB::table($this->table)->first();

        if ($siteSettings) {
            // make sure to decode the json
            return json_decode($siteSettings->{$this->column}, true);
        }
        return null;
    }

    // Method to update the platform lists settings in the database
    public function updateAlertSysRuleSettings($newSettings)
    {
        DB::table($this->table)->updateOrInsert(
            [], // Empty array means update or insert the first row
            [$this->column => $newSettings]
        );

        // delete cached settings
        Cache::forget('platform_lists_settings');

        return true;
    }

    // Method to update the 'status' key in the platform lists settings
    public function updateStatus($status): bool
    {
        $settings = $this->fetchAlertSysSettings();
        $settings['status'] = $status;
        $this->updateAlertSysRuleSettings($settings);
        return true;
    }

    // Method to update the 'status' key in the platform lists settings rules
    public function updateRuleStatus($ruleId, $status): bool
    {
        $settings = $this->fetchAlertSysSettings();

        foreach ($settings['rules'] as &$rule) {
            if ($rule['id'] === $ruleId) {
                $rule['status'] = $status;
            }
        }

        $this->updateAlertSysRuleSettings($settings);
        return true;
    }

    // Method to update the 'check_interval' key in the platform lists settings
    public function updateCheckInterval($label, $value): bool
    {
        $settings = $this->fetchAlertSysSettings();
        $settings['check_interval'] = ['label' => $label, 'value' => $value];
        $this->updateAlertSysRuleSettings($settings);
        return true;
    }

    // Method to update the 'check_period' key in the platform lists settings
    public function updateCheckPeriod($label, $value): bool
    {
        $settings = $this->fetchAlertSysSettings();
        $settings['check_period'] = ['label' => $label, 'value' => $value];
        $this->updateAlertSysRuleSettings($settings);
        return true;
    }

    // Method to update the 'via' key in the platform lists settings
    public function updateVia($via): bool
    {
        $settings = $this->fetchAlertSysSettings();
        $settings['via'] = $via;
        $this->updateAlertSysRuleSettings($settings);
        return true;
    }

    // Method to add a new rule to the platform lists settings
    public function addRule($ruleData): bool
    {
        $settings = $this->fetchAlertSysSettings();
        $settings['rules'][] = $ruleData;
        $this->updateAlertSysRuleSettings($settings);
        return true;
    }

    // Method to update an existing rule in the platform lists settings
    public function updateRule($ruleId, $updatedRuleData): bool
    {
        $settings = $this->fetchAlertSysSettings();

        // Return all settings rules keys
        $rulesKeys = $this->getRuleKeysByRuleId($settings, $ruleId);

        foreach ($settings['rules'] as &$rule) {
            if ($rule['id'] === $ruleId) {
                // Check and update only the keys that are present in the updated rule data
                foreach ($updatedRuleData as $key => $value) {
                    if (in_array($key, $rulesKeys)) {
                        if ($key === 'buyerResponseMatching' && !in_array('None', $value)) {
                            $mergedBuyerResponseMatching = [];

                            foreach ($value as $item) {
                                $header = $item['header'];

                                // Check if the header already exists in mergedBuyerResponseMatching
                                $existingHeader = array_search($header, array_column($mergedBuyerResponseMatching, 'header'));

                                if ($existingHeader !== false) {
                                    // If it exists, merge the values
                                    $mergedBuyerResponseMatching[$existingHeader]['values'] = array_unique(
                                        array_merge($mergedBuyerResponseMatching[$existingHeader]['values'], $item['values'])
                                    );
                                } else {
                                    // If it's a new header, add it to the merged array
                                    $mergedBuyerResponseMatching[] = $item;
                                }
                            }

                            $rule['buyerResponseMatching'] = $mergedBuyerResponseMatching;
                            unset($item);
                        } elseif ($key === 'safeResponse' && !in_array('None', $value)) {
                            $mergedSafeResponse = [];

                            foreach ($value as $item) {
                                $header = $item['header'];

                                // operator
                                $operator = $item['operator'];

                                // Check if the header already exists in mergedSafeResponse
                                $existingHeader = array_search($header, array_column($mergedSafeResponse, 'header'));

                                if ($existingHeader !== false) {
                                    // If it exists, merge the values
                                    $mergedSafeResponse[$existingHeader]['values'] = array_unique(
                                        array_merge($mergedSafeResponse[$existingHeader]['values'], $item['values'])
                                    );
                                } else {
                                    // If it's a new header, add it to the merged array
                                    $mergedSafeResponse[] = $item;
                                }
                            }

                            $rule['safeResponse'] = $mergedSafeResponse;
                            unset($item);
                        } else {
                            $rule[$key] = $value;
                        }
                    }
                }
            }
        }

        $this->updateAlertSysRuleSettings($settings);
        return true;
    }

    // Method to get the rule keys by rule id
    function getRuleKeysByRuleId($data, $chosenId): array
    {
        $keys = [];

        if (isset($data['rules']) && is_array($data['rules'])) {
            foreach ($data['rules'] as $rule) {
                if ($rule['id'] === $chosenId) {
                    $keys = array_keys($rule);
                    break; // Exit the loop after finding the matching rule
                }
            }
        }

        // append new keys if not present like ['ignoreParameters', 'safeResponse']
        $newKeys = ['ignoreParameters', 'safeResponse'];
        if (in_array('buyerResponseMatching', $keys)) {
            $keys = array_merge($keys, $newKeys);
        }

        // remove duplicates
        $keys = array_unique($keys);

        return $keys;
    }

    // Method to delete a rule from the platform lists settings
    public function deleteRule($ruleId): bool
    {
        $settings = $this->fetchAlertSysSettings();
        $filteredRules = array_filter($settings['rules'], function ($rule) use ($ruleId) {
            return $rule['id'] !== $ruleId;
        });
        $settings['rules'] = array_values($filteredRules); // Reindex the array after filtering
        $this->updateAlertSysRuleSettings($settings);
        return true;
    }

    // helper
    private function mergeDuplicateHeaders($updatedRuleData): array
    {
        $uniqueHeaders = [];
        $mergedData = [];

        foreach ($updatedRuleData as $item) {
            $header = $item['header'];

            if (!in_array($header, $uniqueHeaders)) {
                $uniqueHeaders[] = $header;
                $mergedData[] = $item;
            } else {
                // Find the existing item with the same header and merge its values
                foreach ($mergedData as &$existingItem) {
                    if ($existingItem['header'] === $header) {
                        $existingItem['values'] = array_merge($existingItem['values'], $item['values']);
                    }
                }
            }
        }

        return $mergedData;
    }
}
