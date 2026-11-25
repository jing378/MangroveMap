<?php

namespace Tests\Support;

/** Small JPEGs with the camera data a DJI drone writes into its photos (XMP), for photo upload tests. */
class DronePhotoFactory
{
    /**
     * A JPEG whose top half is red and bottom half blue, so tests can tell which way the photo was turned.
     *
     * @param  array<string, string|float>  $xmp  drone-dji attributes (GpsLatitude, RelativeAltitude, ...)
     */
    public static function jpeg(array $xmp, int $width = 400, int $height = 300): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, intdiv($height, 2) - 1, imagecolorallocate($image, 220, 30, 30));
        imagefilledrectangle($image, 0, intdiv($height, 2), $width - 1, $height - 1, imagecolorallocate($image, 30, 30, 220));
        ob_start();
        imagejpeg($image, null, 95);
        $jpeg = (string) ob_get_clean();

        $attributes = implode(' ', array_map(
            fn (string $key, $value) => sprintf('drone-dji:%s="%s"', $key, $value),
            array_keys($xmp),
            $xmp,
        ));
        $packet = '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            .'<rdf:Description xmlns:drone-dji="http://www.dji.com/drone-dji/1.0/" '.$attributes.'/></rdf:RDF></x:xmpmeta>';
        $segment = "\xFF\xE1".pack('n', strlen($packet) + 31).'http://ns.adobe.com/xap/1.0/'."\0".$packet;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2); // right after the JPEG's start marker
    }

    /** Camera data of a photo taken straight down, 30 m above take-off, 0.1 m per pixel at 400 px wide. */
    public static function straightDown(float $lat = 10.3755, float $lng = 125.0144, float $yaw = 0, float $roll = 0): array
    {
        return [
            'GpsLatitude' => sprintf('%+.9f', $lat),
            'GpsLongitude' => sprintf('%+.9f', $lng),
            'RelativeAltitude' => '+30.000',
            'GimbalRollDegree' => sprintf('%+.2f', $roll),
            'GimbalYawDegree' => sprintf('%+.2f', $yaw),
            'GimbalPitchDegree' => '-90.00',
            'CalibratedFocalLength' => '300.000000',
            'CalibratedOpticalCenterX' => '200.000000',
            'CalibratedOpticalCenterY' => '150.000000',
        ];
    }
}
