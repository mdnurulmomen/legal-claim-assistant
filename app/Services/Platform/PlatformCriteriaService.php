<?php

namespace App\Services\Platform;

use Illuminate\Http\Request;

class PlatformCriteriaService {

    /**
     * Get all unique filter values for a given filter key from a list of integrations.
     *
     * @param array $integrations
     * @param string $filterKey
     * @return array
     */
    public function getAllFilterValues(array $integrations, string $filterKey): array
    {
        return collect($integrations)
            ->filter(fn($integration) => ($integration['active'] ?? false) && isset($integration['filter'][$filterKey]))
            ->flatMap(fn($integration) => collect($integration['filter'][$filterKey])
                ->reject(fn($item) => str_starts_with($item, '!'))
                ->map(fn($item) => strtolower($item))
            )
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Group integrations by their buyer names and returns a mapping of buyer names
     * to an array of unique values for the given filter key.
     *
     * The returned array is indexed by buyer names, and each buyer name maps to an
     * array of unique filter values. If a buyer's integration is not active, it is
     * not included in the returned mapping.
     *
     * @param array $integrations
     * @param string $filterKey
     * @return array
     */
    public function getBuyersGroupedByFilterValues(array $integrations, string $filterKey): array
    {
        $groupedBuyers = [];

        foreach ($integrations as $integration) {
            $filter = $integration['filter'] ?? [];
            $buyerName = $integration['name'] ?? [];

            foreach ($filter as $fkey => $fvalue) {
                //remove all items that starts with !
                $filter[$fkey] = array_filter($fvalue, function ($item) {
                    return strpos($item, '!') !== 0;
                });
            }

            // only if $integration['active'] is true
            if (!isset($integration['active']) || $integration['active'] !== true) {
                continue;
            }

            if (isset($filter[$filterKey])) {
                $values = $filter[$filterKey];

                foreach ($values as $value) {
                    if (!isset($groupedBuyers[$buyerName])) {
                        $groupedBuyers[$buyerName] = [];
                    }
                    $groupedBuyers[$buyerName][] = $value;
                }

                // Remove duplicates (case-sensitive)
                if (!empty($groupedBuyers[$buyerName])) {
                    $groupedBuyers[$buyerName] = array_values($this->arrayUnique($groupedBuyers[$buyerName], SORT_REGULAR));
                }
            }
        }

        return $groupedBuyers;
    }

    public function arrayUnique( array $array )
    {
        return array_intersect_key(
            $array,
            array_unique( array_map( "strtolower", $array ) )
        );
    }

}
