<?php

namespace App\Traits;

use Illuminate\Support\Carbon;

trait FormatterTrait {

    /**
     * Calculates the Shannon entropy of a given string.
     *
     * @param string $string
     * @return float|int
     */
    public function calculateStringEntropy($string): float|int
    {
        $len = strlen($string);
        $freq = array_count_values(str_split($string));

        $entropy = 0;

        foreach ($freq as $count) {
            $p = $count / $len;
            $entropy -= $p * log($p, 2);
        }

        return $entropy;
    }

    /**
     * Format a given date and time according to the given format.
     *
     * @param string|null $dateTime
     * @param string $format
     * @param string $timezone
     * @param string $currentTimezone
     * @return string|null Output: Aug 16, 2022 2.54 PM.
     */
    public function formatDateTime(?string $dateTime, string $format = 'M j Y g:i A', string $timezone = 'America/New_York', string $currentTimezone = 'UTC'): ?string
    {
        if(empty($dateTime)) return null;

        $formattedDate = Carbon::createFromFormat('Y-m-d H:i:s', $dateTime, $currentTimezone) // Assuming the input is in UTC
                            ->setTimezone($timezone)
                            ->format($format);

        return $formattedDate;
    }

    /**
     * Convert a given string to a number, if possible.
     *
     * If the given string is a numeric value, this function will convert it to a number.
     * If the given string is not a numeric value, this function will simply return the string.
     *
     * @param string $value
     * @return int|float|string
     */
    public function formatString(string $value): int|float|string
    {
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }

        return $value;
    }
}
