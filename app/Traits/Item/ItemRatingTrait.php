<?php

namespace App\Traits\Item;

trait ItemRatingTrait
{
    public static function averageRating($rating)
    {
        $totalRating = 0;
        $totalRating += $rating[1];
        $totalRating += $rating[2] * 2;
        $totalRating += $rating[3] * 3;
        $totalRating += $rating[4] * 4;
        $totalRating += $rating[5] * 5;

        return $totalRating / array_sum($rating);
    }

    public static function calculateOverallRating($reviews)
    {
        $totalRating = count($reviews);
        $rating = 0;

        foreach ($reviews as $review) {
            $rating += $review->rating;
        }

        return [$totalRating == 0 ? 0 : number_format($rating / $totalRating, 2), $totalRating];
    }

    public static function updateRatingHistogram($ratings, $productRating)
    {
        $storeRatings = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        if (isset($ratings)) {
            $storeRatings = json_decode($ratings, true);
            $storeRatings[$productRating] = $storeRatings[$productRating] + 1;
        } else {
            $storeRatings[$productRating] = 1;
        }

        return json_encode($storeRatings);
    }
}
