<?php

namespace Foundationphp\ImageHandling;
/**
 * Class Scale
 *
 * Acts as a wrapper to PHP GD functions to generate a thumbnail.
 *
 * The thumbnail is saved in a separate folder with the same name
 * as the original file. There's also an option to generate a
 * mirror image of the thumbnail (flipped horizontally).
 *
 * @package Foundationphp\ImageHandling
 */
class Scale
{
    /**
     * @var string Image name - the same name is used for the output file
     */
    protected $filename;

    /**
     * @var string Source folder, defaults to the current folder
     */
    protected $sourceFolder = '.';

    /**
     * @var string Output folder, defaults to "scaled"
     */
    protected $outputFolder = 'scaled';

    /**
     * @var bool Flag that determines whether mirror copy should be made
     */
    protected $flip;

    /**
     * @var null|string Name of output folder for mirror thumbnails
     */
    protected $flipFolder;

    /**
     * @var float Scaling ratio, defaults to 0.05
     */
    protected $ratio = 0.05;

    /**
     * @param string $filename Name of image from which to create thumbnail
     * @param bool $flip Flag to determine whether to create mirror thumbnail
     * @param null|string $flipFolder Output folder for mirror thumbnails
     */
    public function __construct($filename, $flip = false, $flipFolder = null)
    {
        if (!extension_loaded('gd')) {
            throw new \Exception('This class requires the PHP GD extension.');
        }
        $this->filename = $filename;
        $this->flip = $flip;
        if ($this->flip && is_null($flipFolder)) {
            throw new \Exception('You must specify a folder for the flipped originals.');
        }
        if ($this->flip && $flipFolder) {
            if (!is_dir($flipFolder)) {
                if (!mkdir($flipFolder, 0755)) {
                    throw new \Exception("Cannot create folder for flipped originals at $flipFolder.");
                }
            } elseif (!is_writable($flipFolder)) {
                throw new \Exception("$flipFolder must be writable");
            }
        }
        $this->flipFolder = $flipFolder;
    }

    /**
     * Sets source folder (optional). If not set, current folder is used.
     *
     * @param string $folder Folder name
     * @throws \Exception if folder doesn't exist or isn't readable
     */
    public function setSourceFolder($folder)
    {
        if (!is_dir($folder) || !is_readable($folder)) {
            throw new \Exception("Check that $folder exists and is readable.");
        }
        $this->sourceFolder = $folder;
    }

    /**
     * Sets output folder (optional). If not set, default is "scaled".
     *
     * @param string $folder Name of output folder
     */
    public function setOutputFolder($folder)
    {
        $this->outputFolder = $folder;
    }

    /**
     * Sets the scaling ratio (optional). If not set, default is 5 per cent.
     *
     * @param int|float $percentage Percentage expressed as an integer or decimal fraction
     */
    public function setRatio($percentage)
    {
        if (is_numeric($percentage)) {
            if ($percentage > 1) {
                $this->ratio = $percentage / 100;
            } elseif ($percentage > 0) {
                $this->ratio = $percentage;
            }
        }
    }

    /**
     * Generates the thumbnail using PHP GD functions.
     *
     * @throws \Exception if output folder can't be created
     */
    public function create()
    {
        if (!is_dir($this->outputFolder)) {
            if (!mkdir($this->outputFolder, 0755)) {
                throw new \Exception("Cannot create output folder at $this->outputFolder.");
            }
        }
        $source = $this->sourceFolder . '/' . $this->filename;
        $output = $this->outputFolder . '/' . $this->filename;

        // Get image dimensions and MIME type
        list($width, $height, $type) = getimagesize($source);

        // Get correct GD functions depending on MIME type
        $creators = $this->getCorrectFunctions($type);
        if (!$creators) {
            throw new \Exception($this->filename . ' does not appear to be a valid image.');
        }

        // Create image resource for original image
        $original = $creators[0]($source);

        // Calculate dimensions and use them to create resource for thumbnail
        $new_width = $width * $this->ratio;
        $new_height = $height * $this->ratio;
        $resized = imagecreatetruecolor($new_width, $new_height);

        // Create thumbnail and save to file
        imagecopyresampled($resized, $original, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        $creators[1]($resized, $output, 100);

        // Create mirror thumbnail if $flip is set to true
        if ($this->flip) {
            imageflip($resized, IMG_FLIP_HORIZONTAL);
            $creators[1]($resized, $this->flipFolder . '/' . $this->filename, 100);
        }

        // Remove the image resources from memory
        imagedestroy($resized);
        imagedestroy($original);
    }

    /**
     * Selects GD functions to generate image resource and file depending on MIME type
     *
     * @param int $type GD constant for MIME type
     * @return array|bool Array of image functions or false
     */
    protected function getCorrectFunctions($type)
    {
        switch ($type) {
            case IMG_JPG:
                return array('imagecreatefromjpeg', 'imagejpeg');
            case IMG_GIF:
                return array('imagecreatefromgif', 'imagegif');
            case IMG_PNG:
                return array('imagecreatefrompng', 'imagepng');
            case IMG_WBMP:
                return array('imagecreatefromwbmp', 'imagewbmp');
            case IMG_XPM:
                return array('imagecreatefromxpm', 'imagejpeg');
            default:
                return false;
        }
    }
}