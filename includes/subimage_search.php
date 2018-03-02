<?php
function findSubImage($large, $small, $wSmall, $hSmall, $outputFolder) {
    // Compare the two files using ImageMagick compare

    $lastline = exec("compare -metric mae -subimage-search $large $small null: 2>&1");
    $pattern = '/\(([\d.]+)\) @ (\d+),(\d+)/';
    $match = preg_match($pattern, $lastline, $stats);
    if (!$match || count($stats) != 4) {
        return false;
    } elseif ($stats[1] > 0.05) {
        return false;
    } else {
        // Use GD to create two copies of the large image
        $gray = imagecreatefromjpeg($large);
        $color = imagecreatefromjpeg($large);

        // Convert one to grayscale
        imagefilter($gray, IMG_FILTER_GRAYSCALE);

        // Merge the matched section from the second copy into the grayscale one
        imagecopymerge($gray, $color, $stats[2], $stats[3],
            $stats[2], $stats[3], $wSmall, $hSmall, 100);

        // Save the merged copy to disk
        $combined = basename($large);
        imagejpeg($gray, $outputFolder . '/' . $combined);
        // Clean up resources that are no longer needed
        imagedestroy($gray);
        imagedestroy($color);
        return true;
    }
}
