<?php

namespace App\Traits;

use Illuminate\Support\Carbon;

trait FormatterTrait {

    /**
     * Determines if a given string is likely a randomly generated string.
     *
     * @param string $string
     * @return bool
     */
    public function isRandomString($string): bool
    {

        $minLengthForRandom = 8;
        $entropyThreshold = 4.0;

        if (preg_match('/[a-z]{3,}/i', $string)) {
            return false;
        }

        if (strlen($string) < $minLengthForRandom) {
            return false;
        }

        $entropy = $this->calculateStringEntropy($string);

        return $entropy > $entropyThreshold;
    }

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
    public function formatDateTime(?string $dateTime, string $format = 'M j, Y g.i A', string $timezone = 'America/New_York', string $currentTimezone = 'UTC'): ?string
    {
        if(empty($dateTime)) return null;

        $formattedDate = Carbon::createFromFormat('Y-m-d H:i:s', $dateTime, $currentTimezone) // Assuming the input is in UTC
                            ->setTimezone($timezone)
                            ->format($format);

        return $formattedDate;
    }
}
