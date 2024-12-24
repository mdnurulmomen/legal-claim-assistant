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
        $data = $lead['datas'] ?? [];
        unset($lead['datas']);
        $filteredLead = array_filter($lead, fn($value) => !empty($value));
        return array_merge($filteredLead, $data);
    }
}
