<?php

namespace App\Traits;

trait AffiliateTrait {

    /**
     * Format affiliate IDs
     *
     * @param string $affIds Affiliate IDs stored in a JSON string
     * @return string A comma-separated string of affiliate IDs
     */
    public function formatAffIds(?string $affIds): string
    {
        if(empty($affIds)) return '';
        return implode(', ',(array_unique(array_map('strval', json_decode($affIds, true)))));
    }
}
