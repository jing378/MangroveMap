<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\DelineationAnnualObservation;
use App\Models\DroneImageryLayer;
use App\Models\User;
use App\Notifications\DelineationReassessed;
use App\Notifications\DelineationSubmittedForReview;
use App\Services\ApprovedCoverageStats;
use App\Services\DroneImageryDelineationPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A drone survey over an area already on the map can be submitted as a re-survey of it. Once approved, its
 * boundary updates that area as the survey year's observation, and it is not drawn or counted as a second area.
 */
class DroneReassessmentTest extends TestCase
{
    use RefreshDatabase;

    /** The area as first mapped ([lat, lng]). */
    private const ORIGINAL = [[9.95, 124.05], [9.95, 124.06], [9.96, 124.06], [9.96, 124.05]];

    /** The re-survey: the same place, a bit smaller (mangroves lost on one side). */
    private const RESURVEY = [[9.95, 124.05], [9.95, 124.058], [9.96, 124.058], [9.96, 124.05]];

    /** Somewhere else entirely. */
    private const ELSEWHERE = [[10.2, 124.3], [10.2, 124.31], [10.21, 124.31], [10.21, 124.3]];

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection()->getPdo()
            ->sqliteCreateFunction('YEAR', fn ($date) => $date ? (int) substr($date, 0, 4) : null, 1);
    }

    private function area(User $owner, array $coords, array $attributes = []): Delineation
    {
        return Delineation::create([
            'user_id' => $owner->id,
            'name' => 'Cabalian stand',
            'features' => [['type' => 'area', 'coords' => $coords, 'genera' => ['Rhizophora']]],
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => $owner->id,
            ...$attributes,
        ]);
    }

    private function layer(User $owner, string $status, array $coords, array $attributes = []): DroneImageryLayer
    {
        $layer = DroneImageryLayer::create([
            'user_id' => $owner->id,
            'name' => 'Re-survey 2026',
            'status' => $status,
            'source_type' => 'single',
            'bounds' => ['west' => 124.0, 'south' => 9.9, 'east' => 124.1, 'north' => 10.0],
            'preview_path' => 'preview.png',
            'preview_bounds' => ['west' => 124.0, 'south' => 9.9, 'east' => 124.1, 'north' => 10.0],
            'zoom_min' => 12,
            'zoom_max' => 20,
            ...$attributes,
        ]);
        $path = "drone-imagery/processed/{$layer->id}/ai_extent.json";
        Storage::disk('local')->put($path, json_encode(['features' => [['type' => 'area', 'coords' => $coords]]]));
        $layer->update([
            'tiles_path' => "drone-imagery/tiles/{$layer->id}",
            'segmentation_status' => 'ready',
            'segmentation_path' => $path,
        ]);

        return $layer->fresh();
    }

    private function edits(): array
    {
        return ['mode' => 'remove', 'polygons' => [], 'offset' => ['east_m' => 0, 'north_m' => 0], 'opacity' => 0.8];
    }

    public function test_the_editor_offers_the_approved_areas_the_survey_overlaps(): void
    {
        Storage::fake('local');
        Queue::fake();
        $expert = User::factory()->create(['role' => 'expert']);
        $resident = User::factory()->create(['role' => 'end_user']);
        $overlapped = $this->area($expert, self::ORIGINAL);
        $this->area($expert, self::ELSEWHERE, ['name' => 'Far away']);
        $this->area($resident, self::ORIGINAL, ['name' => 'Still pending', 'is_approved' => false, 'approved_at' => null, 'approved_by' => null]);
        $layer = $this->layer($resident, 'awaiting_review', self::RESURVEY);

        $this->actingAs($resident)
            ->get(route('drone-imagery.edit', $layer->id))
            ->assertOk()
            ->assertSee('Survey of')
            ->assertViewHas('editor', function (array $editor) use ($overlapped) {
                $candidates = $editor['reassessment_candidates'];

                return count($candidates) === 1
                    && $candidates[0]['id'] === $overlapped->id
                    && $candidates[0]['overlap_percent'] > 95; // the re-survey lies inside the original area
            });
    }

    public function test_confirm_keeps_the_chosen_area_and_refuses_one_the_survey_does_not_overlap(): void
    {
        Storage::fake('local');
        Queue::fake();
        $expert = User::factory()->create(['role' => 'expert']);
        $resident = User::factory()->create(['role' => 'end_user']);
        $overlapped = $this->area($expert, self::ORIGINAL);
        $elsewhere = $this->area($expert, self::ELSEWHERE, ['name' => 'Far away']);
        $layer = $this->layer($resident, 'awaiting_review', self::RESURVEY);
        $details = ['name' => 'Cabalian 2026', 'genera' => ['Rhizophora'], 'notes' => null];

        $this->actingAs($resident)
            ->postJson(route('drone-imagery.confirm', $layer->id), [
                ...$this->edits(),
                'delineation' => [...$details, 'reassesses_delineation_id' => $elsewhere->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delineation.reassesses_delineation_id');

        $this->actingAs($resident)
            ->postJson(route('drone-imagery.confirm', $layer->id), [
                ...$this->edits(),
                'delineation' => [...$details, 'reassesses_delineation_id' => $overlapped->id],
            ])
            ->assertAccepted();

        $this->assertSame($overlapped->id, $layer->fresh()->delineation_details['reassesses_delineation_id']);
    }

    public function test_an_approved_resident_resurvey_updates_the_area_once_instead_of_adding_a_second(): void
    {
        Storage::fake('local');
        $expert = User::factory()->create(['role' => 'expert']);
        $resident = User::factory()->create(['role' => 'end_user']);
        $this->travelTo(now()->setDate(2024, 6, 1));
        $original = $this->area($expert, self::ORIGINAL);
        $this->travelBack();
        $originalHa = ApprovedCoverageStats::areaHectares($original->features);

        Notification::fake();
        $layer = $this->layer($resident, 'ready', self::RESURVEY, [
            'delineation_details' => [
                'name' => 'Cabalian 2026', 'genera' => ['Avicennia'], 'notes' => null,
                'reassesses_delineation_id' => $original->id,
            ],
        ]);
        $survey = app(DroneImageryDelineationPublisher::class)->publish($layer);

        // Pending: the expert is told it is a re-survey, and nothing changed yet.
        $this->assertSame($original->id, $survey->reassesses_delineation_id);
        $this->assertFalse((bool) $survey->is_approved);
        Notification::assertSentTo($expert, DelineationSubmittedForReview::class, function ($notification) use ($expert) {
            return str_contains($notification->toArray($expert)['message'], 're-survey of "Cabalian stand"');
        });
        $this->assertEqualsWithDelta($originalHa, app(ApprovedCoverageStats::class)->summary()['area_ha'], 0.01);

        $this->actingAs($expert)
            ->postJson(route('expert.delineations.approve', $survey))
            ->assertOk()
            ->assertJsonPath('reassessed_delineation_id', $original->id);

        // The original area now has the re-surveyed boundary, recorded as this year's observation.
        $original->refresh();
        $resurveyHa = ApprovedCoverageStats::areaHectares($original->features);
        $this->assertSame(self::RESURVEY, $original->features[0]['coords']);
        $this->assertSame('Cabalian stand', $original->name);
        $observation = $original->annualObservations()->where('year', now()->year)->first();
        $this->assertNotNull($observation);
        $this->assertSame(DelineationAnnualObservation::SOURCES['drone_imagery'], $observation->source);
        $this->assertEqualsWithDelta($resurveyHa, $observation->coverage_area_ha, 0.01);
        $this->assertTrue($original->annualObservations()->where('year', 2024)->exists(), 'The first observation also keeps the baseline.');
        $this->assertSame('reassessment', $original->revisions()->latest('version')->first()->snapshot['type']);

        // Counted once: the hectares are the re-surveyed area, not both.
        $this->assertEqualsWithDelta($resurveyHa, app(ApprovedCoverageStats::class)->summary()['area_ha'], 0.01);

        // Not drawn as a second area: the resident sees the updated original instead of their survey.
        $map = $this->actingAs($resident)->getJson(route('map.delineations'))->assertOk();
        $this->assertSame([], collect($map->json('delineations'))->pluck('id')->all());
        $this->assertContains($original->id, collect($map->json('approvedDelineationsForMap'))->pluck('id')->all());

        // The imagery itself is published, like any approved drone survey.
        $this->assertTrue($layer->fresh()->isPublished());
    }

    public function test_the_area_owner_is_told_when_someone_elses_resurvey_updates_it(): void
    {
        Storage::fake('local');
        $areaOwner = User::factory()->create(['role' => 'end_user', 'name' => 'Area Owner']);
        $surveyor = User::factory()->create(['role' => 'end_user', 'name' => 'Drone Pilot']);
        $expert = User::factory()->create(['role' => 'expert']);
        $original = $this->area($areaOwner, self::ORIGINAL, ['approved_by' => $expert->id]);
        $layer = $this->layer($surveyor, 'ready', self::RESURVEY, [
            'delineation_details' => [
                'name' => 'Pilot survey', 'genera' => ['Rhizophora'], 'notes' => null,
                'reassesses_delineation_id' => $original->id,
            ],
        ]);
        Notification::fake();
        $survey = app(DroneImageryDelineationPublisher::class)->publish($layer);
        Notification::assertNothingSentTo($areaOwner); // not before an expert approves it

        $this->actingAs($expert)->postJson(route('expert.delineations.approve', $survey))->assertOk();

        Notification::assertSentTo($areaOwner, DelineationReassessed::class, function (DelineationReassessed $notification) use ($areaOwner, $original) {
            $data = $notification->toArray($areaOwner);

            return $data['delineation_id'] === $original->id
                && $data['type'] === 'delineation_reassessed'
                && str_contains($data['message'], 'by Drone Pilot updated the boundary of "Cabalian stand" for '.now()->year);
        });
        Notification::assertNotSentTo($surveyor, DelineationReassessed::class);
        Notification::assertNotSentTo($expert, DelineationReassessed::class);
    }

    public function test_an_experts_resurvey_updates_the_area_straight_away(): void
    {
        Storage::fake('local');
        Notification::fake();
        $expert = User::factory()->create(['role' => 'expert']);
        $original = $this->area($expert, self::ORIGINAL);
        $layer = $this->layer($expert, 'ready', self::RESURVEY, [
            'delineation_details' => [
                'name' => null, 'genera' => ['Rhizophora'], 'notes' => null,
                'reassesses_delineation_id' => $original->id,
            ],
        ]);

        $survey = app(DroneImageryDelineationPublisher::class)->publish($layer);

        $this->assertTrue($survey->isAppliedReassessment());
        $this->assertSame(self::RESURVEY, $original->fresh()->features[0]['coords']);
        Notification::assertNotSentTo($expert, DelineationReassessed::class); // their own area
        $this->assertSame(1, Delineation::approved()->withoutAppliedReassessments()->count());
    }

    public function test_a_new_area_choice_or_a_removed_original_keeps_the_survey_as_its_own_area(): void
    {
        Storage::fake('local');
        Notification::fake();
        $expert = User::factory()->create(['role' => 'expert']);
        $original = $this->area($expert, self::ORIGINAL);
        $layer = $this->layer($expert, 'ready', self::RESURVEY, [
            'delineation_details' => [
                'name' => null, 'genera' => ['Rhizophora'], 'notes' => null,
                'reassesses_delineation_id' => $original->id,
            ],
        ]);
        $survey = app(DroneImageryDelineationPublisher::class)->publish($layer);

        $original->delete();

        $survey->refresh();
        $this->assertNull($survey->reassesses_delineation_id);
        $this->assertFalse($survey->isAppliedReassessment());
        $this->assertEqualsWithDelta(
            ApprovedCoverageStats::areaHectares($survey->features),
            app(ApprovedCoverageStats::class)->summary()['area_ha'],
            0.01
        );
    }
}
