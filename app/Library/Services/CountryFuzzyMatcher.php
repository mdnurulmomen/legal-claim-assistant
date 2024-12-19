<?php

namespace App\Library\Services;

use Illuminate\Support\Collection;
use Propaganistas\LaravelPhone\PhoneNumber;

class CountryFuzzyMatcher
{
    /**
     * Comprehensive list of countries with multiple aliases
     * @var array
     */
    private $countries = [
        'US' => ['united states', 'usa', 'u.s', 'u.s.a', 'united states of america'],
        'NL' => ['netherlands', 'holland', 'netherland'],
        'UK' => ['united kingdom', 'britain', 'great britain'],
        'CA' => ['canada', 'canadian'],
        'AU' => ['australia', 'australian'],
        // Add more countries as needed
    ];

    /**
     * Calculate Levenshtein distance between two strings
     *
     * @param string $str1
     * @param string $str2
     * @return int
     */
    private function levenshteinDistance(string $str1, string $str2): int
    {
        return levenshtein(
            mb_strtolower($str1),
            mb_strtolower($str2)
        );
    }

    /**
     * Find the closest country match
     *
     * @param string $input
     * @param int $maxDistance Maximum allowed Levenshtein distance
     * @return string|null
     */
    public function findCountryCode(string $input, int $maxDistance = 3): ?string
    {
        // Normalize input
        $normalizedInput = mb_strtolower(trim($input));

        // Flat list of all possible country names
        $allCountryNames = collect($this->countries)
            ->flatMap(function ($aliases, $countryCode) {
                return collect($aliases)->map(function ($alias) use ($countryCode) {
                    return [
                        'code' => $countryCode,
                        'name' => $alias
                    ];
                });
            });

        // Find the closest match
        $closestMatch = $allCountryNames
            ->map(function ($country) use ($normalizedInput) {
                return [
                    'code' => $country['code'],
                    'distance' => $this->levenshteinDistance($normalizedInput, $country['name'])
                ];
            })
            ->sortBy('distance')
            ->first();

        // Return country code if within acceptable distance
        return ($closestMatch && $closestMatch['distance'] <= $maxDistance)
            ? $closestMatch['code']
            : null;
    }

    /**
     * Validate and format phone number
     *
     * @param string $phoneNumber
     * @param string $countryInput
     * @return array
     */
    public function formatPhoneNumber(string $phoneNumber, ?string $countryInput = null)
    {
        $countryCode = "US";

        if(! empty($countryInput)) {
            $countryCode = $this->findCountryCode($countryInput) ?? $countryCode;
        }

        try {
            $phone = new PhoneNumber($phoneNumber, $countryCode);
            return $phone->formatE164();
        } catch (\Exception $e) {
            return $phoneNumber;
        }
    }
}
