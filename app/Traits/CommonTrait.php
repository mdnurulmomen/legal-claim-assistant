<?php

namespace App\Traits;

use Illuminate\Support\Carbon;

trait CommonTrait
{
    /**
     * Formats the start and end dates with a given timezone.
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string|null $timezone
     * @param bool $isReturnDateObj
     * @return array
     */
    public function formatStartEndDateWithTimezone( string $startDate = null, string $endDate = null, string $timezone = null, bool $isReturnDateObj = false, $defaultTimezone = 'Europe/Amsterdam'): array
    {
        if(empty($startDate) || empty($endDate)){
            return [$startDate, $endDate];
        }

        if(empty($timezone)){
            $timezone = $defaultTimezone;
        }

        $reportStart = Carbon::parse($startDate, $timezone);
        $reportEnd = Carbon::parse($endDate, $timezone);

        if ($timezone !== $defaultTimezone) {
            $reportStart->setTimezone($defaultTimezone);
            $reportEnd->setTimezone($defaultTimezone);
        }

        if($isReturnDateObj){
            return [$reportStart, $reportEnd];
        }

        $reportStart = $reportStart->toDateTimeString();
        $reportEnd = $reportEnd->toDateTimeString();

        return [$reportStart, $reportEnd];
    }

    /**
     * Converts a one-dimensional associative array into a two-dimensional array.
     *
     * @param array $array
     * @return array
     */
    public function convertToMultiDimensionalArray(array $array, bool $isValueUpperCase = false): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[] = [
                'label' => $value,
                'value' => $isValueUpperCase ? strtoupper($value) : $key
            ];
        }
        return $result;
    }
}
