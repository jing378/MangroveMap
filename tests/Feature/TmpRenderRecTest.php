<?php
namespace Tests\Feature;
use App\Models\Delineation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class TmpRenderRecTest extends TestCase
{
    use RefreshDatabase;
    public function test_render(): void
    {
        $r = User::factory()->create(['role' => 'end_user']);
        $e = User::factory()->create(['role' => 'expert']);
        Delineation::create(['user_id' => $r->id, 'name' => 'Ref', 'notes' => 'n', 'is_approved' => true, 'features' => [['type' => 'area', 'coords' => [[10, 124], [10, 124.01], [10.01, 124.01], [10, 124]]]]]);
        file_put_contents(getenv('SNAP_DIR') . '/rec.html', $this->actingAs($e)->get(route('expert.recommendations.index'))->assertOk()->getContent());
        $this->assertTrue(true);
    }
}
