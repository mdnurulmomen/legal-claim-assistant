<?php

namespace App\Services\Public;

class PublicService
{

    /**
     * Formats a lead by separating its data attribute and merging non-empty attributes.
     *
     * @param array $lead
     * @return array
     */
    public function formatLead(array $lead): array
    {
        $headers = json_decode($lead['lead_headers'], true);
        $data = $lead['datas'] ?? [];

        array_push($headers, 'id', 'list_name');
        unset($lead['datas'], $lead['lead_headers']);

        $mergedLead = array_merge($lead, $data);
        return array_filter($mergedLead, fn($value, $key) => !empty($value) && in_array($key, $headers), ARRAY_FILTER_USE_BOTH);
    }
}
