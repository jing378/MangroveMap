<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class TmpRenderSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot(): void
    {
        $dir = getenv('SNAP_DIR');
        $resident = User::factory()->create(['role' => 'end_user', 'name' => 'Res']);
        $expert = User::factory()->create(['role' => 'expert', 'name' => 'Exp']);
        $rec = ['id' => 7, 'name' => 'Z', 'notes' => 'n', 'is_approved' => true, 'features' => [['type' => 'area', 'coords' => [[10, 124], [10, 124.01], [10.01, 124.01], [10, 124]]]]];
        foreach ([[$expert, 'expert.delineate', 'users.delineate', 'delineate'], [$expert, 'expert.map', 'users.map', 'map'], [$resident, 'delineate', 'users.delineate', 'delineate'], [$resident, 'map', 'users.map', 'map']] as [$u, $r, $view, $page]) {
            $this->actingAs($u);
            $route = new Route(['GET'], '/x', ['uses' => fn() => null]);
            $route->name($r);
            $request = Request::create('/x', 'GET');
            $request->setRouteResolver(fn() => $route);
            $request->setUserResolver(fn() => $u);
            $this->app->instance('request', $request);
            $html = view($view, ['delineations' => [$rec], 'approvedDelineationsForMap' => [$rec], 'residentDelineationsForMap' => [$rec],
                'focusDelineationId' => null, 'focusDelineationRecord' => null, 'page' => $page, 'totalCoverage' => 1, 'genusCount' => 1])->render();
            $html = preg_replace('/name="csrf-token" content="[^"]+"|name="_token" value="[^"]+"/', '', $html);
            file_put_contents("$dir/$r.html", $html);
        }
        $this->assertTrue(true);
    }
}
