<?php
ini_set('max_execution_time', 0);
$start = microtime(true);

require_once 'Foundationphp/ImageHandling/Scale.php';
require_once 'includes/subimage_search.php';

use Foundationphp\ImageHandling\Scale;

$version = phpversion();
if ($version >= '5.5.0') {
    define('CAN_FLIP', true);
} else {
    define('CAN_FLIP', false);
}

// Image folders
$imageDir = 'originals';
$scaledDir = 'scaled';
$flippedDir = 'mirror';
$matchedDir = 'pirated';

// Files and dimensions
$files = array();
$widths = array();
$heights = array();

$dir = new DirectoryIterator($imageDir);
$images = new RegexIterator($dir, '/\.jpg$/i');
foreach ($images as $image) {
    $filename = $image->getFilename();
    $files[] = $filename;
    list($widths[], $heights[]) = getimagesize($imageDir . '/' . $filename);
}
$smallest = min(array_merge($widths, $heights));
$ratio = round(25 / $smallest, 2);
unset($widths);
unset($heights);

$resized = array();
try {
    for ($i = 0; $i < count($files); $i++) {
        if (CAN_FLIP) {
            $scaled = new Scale($files[$i], true, $flippedDir);
        } else {
            $scaled = new Scale($files[$i]);
        }
        $scaled->setSourceFolder($imageDir);
        $scaled->setRatio($ratio);
        $scaled->create();
        $resized[$i]['name'] = $files[$i];
        list($resized[$i]['w'], $resized[$i]['h']) =
            getimagesize($scaledDir . '/'. $files[$i]);
    }
    unset($files);
} catch (Exception $e) {
    echo $e->getMessage();
}

$pirated = array();
$p = 0;

while (count($resized) > 1) {
    $reference = array_shift($resized);
    $ref = $scaledDir . '/' . $reference['name'];
    foreach ($resized as $candidate) {
        if ($candidate['w'] > $reference['w'] || $candidate['h'] >
            $reference['h']) {
            continue;
        } else {
            $normal = $scaledDir . '/' . $candidate['name'];
            $flipped = $flippedDir . '/' . $candidate['name'];
            if (findSubImage($ref, $normal, $candidate['w'], $candidate['h'],
            $matchedDir)) {
                $pirated[$p][0] = $ref;
                $pirated[$p][1] = $normal;
                $p++;
            } elseif (CAN_FLIP && findSubImage($ref, $flipped, $candidate['w'],
                $candidate['h'],
                $matchedDir)) {
                $pirated[$p][0] = $ref;
                $pirated[$p][1] = $flipped;
                $pirated[$p][2] = 'Flipped horizontally and cropped';
                $p++;
            }
        }
    }
}

if ($pirated) {
    echo '<table>';
    echo '<tr><th>Original</th><th>Cropped</th><th>Area of Sub-Image</th></tr>';
    foreach ($pirated as $row) {
        echo '<tr><td>'. basename($row[0]) . '</td><td>' . basename($row[1]);
        if (isset($row[2])) {
            echo '<br>' . $row[2];
        }
        echo '</td>';
        echo '<td><img src="' . $matchedDir . '/' . basename($row[0]) . '"></td></tr>';
    }
    echo '</table>';
} else {
    echo '<p>No sub-images found.</p>';
}


$end = microtime(true);
echo '<p>Time taken: ' . ($end - $start) . ' seconds.</p>';