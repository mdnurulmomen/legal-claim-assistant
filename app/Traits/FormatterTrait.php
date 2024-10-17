<?php

namespace App\Traits;

trait FormatterTrait {

    /**
     * Determines if a given string is likely a randomly generated string.
     *
     * A string is considered random if it is at least 8 characters long and
     * has a Shannon entropy of at least 4.0. Additionally, the string must
     * not contain at least 3 consecutive alphabetic characters.
     *
     * @param string $string
     *
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
     * The Shannon entropy measures the amount of information in a string. It is
     * calculated by summing the negative logarithm of the probability of each
     * character in the string. The probability of a character is the number of
     * times it appears divided by the total length of the string.
     *
     * @param string $string The string for which to calculate the entropy.
     *
     * @return float|int The Shannon entropy of the given string.
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
}
