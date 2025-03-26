<?php

namespace App\Library\Services;


class BestMatchSearch
{
    /**
     * Find the best match for a given keyword from a list of items.
     *
     * @param array $data
     * @param string $keyword
     * @return string|null
     */
    public function findBestMatch($data, string $keyword): ?string
    {

        if(in_array($keyword, $data)) {
            return $keyword;
        }

        $bestMatch = "";
        $highestScore = 0;

        foreach ($data as $item) {
            $score = $this->calculateMatchScore($item, $keyword);

            if ($score > $highestScore) {
                $highestScore = $score;

                $bestMatch = $item;
            }
        }

        return $bestMatch;
    }

    /**
     * Calculate match score based on multiple criteria
     *
     * @param string $item Individual item to score
     * @param string $keyword Search keyword
     * @return float Matching score
     */
    protected function calculateMatchScore(string $item, string $keyword): float
    {
        if (strtolower($item) === strtolower($keyword)) {
            return 100.0;
        }

        $matchPosition = stripos($item, $keyword);

        if ($matchPosition === false) {
            return 0.0;
        }

        $lengthScore = strlen($keyword) / strlen($item);
        $positionScore = 1 - ($matchPosition / strlen($item));

        $totalScore = (($lengthScore * 0.4) + ($positionScore * 0.6)) * 100;

        return $totalScore;
    }
}