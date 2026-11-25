<?php

namespace Tests\Unit;

use App\Services\DronePhotoGeoreferencer;
use PHPUnit\Framework\TestCase;
use Tests\Support\DronePhotoFactory;

class DronePhotoGeoreferencerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('drone-photo-', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: []);
        rmdir($this->directory);
        parent::tearDown();
    }

    private function photo(array $xmp): string
    {
        $path = $this->directory.DIRECTORY_SEPARATOR.uniqid('photo-').'.jpg';
        file_put_contents($path, DronePhotoFactory::jpeg($xmp));

        return $path;
    }

    public function test_reads_the_camera_data_of_a_dji_photo(): void
    {
        $camera = (new DronePhotoGeoreferencer)->readCamera($this->photo(DronePhotoFactory::straightDown(10.5, 125.1, yaw: 30)));

        $this->assertSame(400, $camera['width']);
        $this->assertSame(300, $camera['height']);
        $this->assertEqualsWithDelta(10.5, $camera['lat'], 1e-9);
        $this->assertEqualsWithDelta(125.1, $camera['lng'], 1e-9);
        $this->assertEqualsWithDelta(30.0, $camera['height_m'], 1e-9);
        $this->assertEqualsWithDelta(300.0, $camera['focal_px'], 1e-9);
        $this->assertEqualsWithDelta(-90.0, $camera['pitch'], 1e-9);
        $this->assertEqualsWithDelta(30.0, $camera['heading'], 1e-9);
    }

    public function test_straight_down_with_the_gimbal_rolled_over_the_photo_faces_the_other_way(): void
    {
        // DJI reports roll 180 and a turned-around yaw for photos taken straight down.
        $camera = (new DronePhotoGeoreferencer)->readCamera($this->photo(DronePhotoFactory::straightDown(yaw: -142, roll: 180)));

        $this->assertEqualsWithDelta(38.0, $camera['heading'], 1e-9);
    }

    public function test_photos_without_camera_data_have_no_camera(): void
    {
        $georeferencer = new DronePhotoGeoreferencer;
        $withoutHeight = DronePhotoFactory::straightDown();
        unset($withoutHeight['RelativeAltitude']);

        $this->assertNull($georeferencer->readCamera($this->photo([])));
        $this->assertNull($georeferencer->readCamera($this->photo($withoutHeight)));
    }

    public function test_file_kinds_and_the_names_pictures_share_with_their_location_files(): void
    {
        $this->assertSame('geotiff', DronePhotoGeoreferencer::kind('Survey.TIFF'));
        $this->assertSame('picture', DronePhotoGeoreferencer::kind('DJI_0001_V.JPG'));
        $this->assertSame('picture', DronePhotoGeoreferencer::kind('tile.png'));
        $this->assertSame('sidecar', DronePhotoGeoreferencer::kind('DJI_0001_V.jgw'));
        $this->assertSame('sidecar', DronePhotoGeoreferencer::kind('tile.jpg.aux.xml'));
        $this->assertNull(DronePhotoGeoreferencer::kind('notes.txt'));

        $this->assertSame('dji_0001_v', DronePhotoGeoreferencer::stem('DJI_0001_V.JPG'));
        $this->assertSame('dji_0001_v', DronePhotoGeoreferencer::stem('DJI_0001_V.jgw'));
        $this->assertSame('tile', DronePhotoGeoreferencer::stem('tile.jpg.aux.xml'));

        $this->assertSame('uploads/1/abc.jgw', DronePhotoGeoreferencer::sidecarPath('uploads/1/abc.jpg', 'Tile.JGW'));
        $this->assertSame('uploads/1/abc.prj', DronePhotoGeoreferencer::sidecarPath('uploads/1/abc.jpg', 'tile.prj'));
        $this->assertSame('uploads/1/abc.jpg.aux.xml', DronePhotoGeoreferencer::sidecarPath('uploads/1/abc.jpg', 'tile.jpg.aux.xml'));
    }
}
