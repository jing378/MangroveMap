<?php

namespace App\Services;

use App\Models\Delineation;
use App\Models\DelineationAnnualObservation;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Facades\DB;

/**
 * Drone re-surveys of an already mapped area. A survey whose areas overlap approved delineations is offered
 * those as "re-survey of …" in the review editor. Once the re-survey is approved (by an expert, or at once when
 * an expert flew it), its boundary is copied onto that area as the survey year's observation, so the area's
 * "Changes over time" shows the change and the hectares are counted once, on the original area.
 */
class DelineationReassessment
{
    /** Most areas offered as "re-survey of …". */
    public const MAX_CANDIDATES = 5;

    /** Grid samples per side when measuring how much of a survey lies inside an area. */
    private const SAMPLES_PER_SIDE = 60;

    public function __construct(private DelineationObservationRecorder $observations) {}

    /**
     * Approved areas the survey's areas overlap, most overlap first.
     *
     * @param  array<int, array{type?: string, coords: array}>  $areas  survey areas, coords in [lat, lng]
     * @return array<int, array{id: int, name: string, location: ?string, area_ha: float, overlap_percent: float, mapped_by: ?string, approved_year: ?int}>
     */
    public function candidatesFor(array $areas): array
    {
        $surveyRings = self::rings($areas);
        if ($surveyRings === []) {
            return [];
        }
        $surveyBox = self::boundingBox($surveyRings);

        return Delineation::approved()
            ->where('is_rejected', false)
            ->whereNull('reassesses_delineation_id')
            ->with('user:id,name')
            ->get(['id', 'user_id', 'name', 'features', 'approved_at', 'created_at'])
            ->map(function (Delineation $delineation) use ($surveyRings, $surveyBox) {
                $rings = self::rings($delineation->features ?? []);
                if ($rings === [] || ! self::boxesOverlap($surveyBox, self::boundingBox($rings)) || ! self::ringsIntersect($surveyRings, $rings)) {
                    return null;
                }

                $features = $delineation->features ?? [];

                return [
                    'id' => $delineation->id,
                    'name' => $delineation->name ?: "Delineation {$delineation->id}",
                    'location' => implode('; ', ApprovedCoverageStats::barangayNames($features)) ?: null,
                    'area_ha' => round(ApprovedCoverageStats::areaHectares($features), 2),
                    'overlap_percent' => round(self::shareInside($surveyRings, $rings) * 100, 1),
                    'mapped_by' => $delineation->user?->name,
                    'approved_year' => ($delineation->approved_at ?? $delineation->created_at)?->year,
                ];
            })
            ->filter()
            ->sortByDesc('overlap_percent')
            ->take(self::MAX_CANDIDATES)
            ->values()
            ->all();
    }

    /**
     * Copy an approved re-survey's boundary onto the area it reassesses. Returns that area, or null when there is
     * nothing to apply. When the area is gone or no longer approved, the survey stands as an area of its own.
     */
    public function apply(Delineation $survey, User $approver): ?Delineation
    {
        if (! $survey->isAppliedReassessment()) {
            return null;
        }

        $target = DB::transaction(function () use ($survey, $approver) {
            $target = Delineation::query()->lockForUpdate()->find($survey->reassesses_delineation_id);
            if ($target === null || ! $target->is_approved || $target->is_rejected || $target->reassesses_delineation_id !== null) {
                $survey->update(['reassesses_delineation_id' => null]);

                return null;
            }

            $previous = $target->features ?? [];
            $features = $survey->features ?? [];
            $target->recordRevision($approver, 'reassessment');
            $this->observations->record(
                $target,
                $previous,
                $features,
                ($survey->created_at ?? now())->copy()->timezone(DelineationAnnualObservation::LOCAL_TIMEZONE),
                DelineationAnnualObservation::SOURCES['drone_imagery'],
                $approver,
            );
            // Only the boundary: the area keeps its name, notes, owner and review.
            $target->update(['features' => $features]);

            return $target;
        });

        if ($target !== null) {
            UserActivity::record(
                $approver,
                'Updated "'.($target->name ?: "Delineation {$target->id}").'" from drone re-survey: '.($survey->name ?: "Delineation {$survey->id}"),
                'Expert Review',
                'approved',
                $target,
            );
        }

        return $target;
    }

    /**
     * Closed rings of the area features, as [lat, lng] float pairs.
     *
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    private static function rings(array $features): array
    {
        $rings = [];
        foreach ($features as $feature) {
            if (! is_array($feature) || ($feature['type'] ?? 'area') !== 'area' || ! is_array($feature['coords'] ?? null)) {
                continue;
            }
            $ring = [];
            foreach ($feature['coords'] as $point) {
                if (is_array($point) && is_numeric($point[0] ?? null) && is_numeric($point[1] ?? null)) {
                    $ring[] = [(float) $point[0], (float) $point[1]];
                }
            }
            if (count($ring) >= 3) {
                $rings[] = $ring;
            }
        }

        return $rings;
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} min lat, min lng, max lat, max lng */
    private static function boundingBox(array $rings): array
    {
        $points = array_merge(...$rings);
        $lats = array_column($points, 0);
        $lngs = array_column($points, 1);

        return [min($lats), min($lngs), max($lats), max($lngs)];
    }

    private static function boxesOverlap(array $a, array $b): bool
    {
        return $a[0] <= $b[2] && $b[0] <= $a[2] && $a[1] <= $b[3] && $b[1] <= $a[3];
    }

    /** Any area of one set touches any area of the other (a corner inside, or crossing edges). */
    private static function ringsIntersect(array $a, array $b): bool
    {
        foreach ($a as $ringA) {
            foreach ($b as $ringB) {
                if (! self::boxesOverlap(self::boundingBox([$ringA]), self::boundingBox([$ringB]))) {
                    continue;
                }
                if (self::insideRing($ringA[0], $ringB) || self::insideRing($ringB[0], $ringA)) {
                    return true;
                }
                for ($i = 0, $n = count($ringA); $i < $n; $i++) {
                    for ($j = 0, $m = count($ringB); $j < $m; $j++) {
                        if (self::segmentsCross($ringA[$i], $ringA[($i + 1) % $n], $ringB[$j], $ringB[($j + 1) % $m])) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /** Share (0–1) of the survey's area that lies inside the other areas, from a grid of samples. */
    private static function shareInside(array $surveyRings, array $rings): float
    {
        [$south, $west, $north, $east] = self::boundingBox($surveyRings);
        $inSurvey = 0;
        $inBoth = 0;
        for ($row = 0; $row < self::SAMPLES_PER_SIDE; $row++) {
            $lat = $south + ($north - $south) * ($row + 0.5) / self::SAMPLES_PER_SIDE;
            for ($col = 0; $col < self::SAMPLES_PER_SIDE; $col++) {
                $point = [$lat, $west + ($east - $west) * ($col + 0.5) / self::SAMPLES_PER_SIDE];
                if (! self::insideAny($point, $surveyRings)) {
                    continue;
                }
                $inSurvey++;
                if (self::insideAny($point, $rings)) {
                    $inBoth++;
                }
            }
        }

        return $inSurvey > 0 ? $inBoth / $inSurvey : 0.0;
    }

    private static function insideAny(array $point, array $rings): bool
    {
        foreach ($rings as $ring) {
            if (self::insideRing($point, $ring)) {
                return true;
            }
        }

        return false;
    }

    /** Ray casting (even-odd), in plain lat/lng: fine at the size of a mangrove area. */
    private static function insideRing(array $point, array $ring): bool
    {
        [$y, $x] = $point;
        $inside = false;
        for ($i = 0, $j = count($ring) - 1, $n = count($ring); $i < $n; $j = $i++) {
            [$yi, $xi] = $ring[$i];
            [$yj, $xj] = $ring[$j];
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private static function segmentsCross(array $p1, array $p2, array $q1, array $q2): bool
    {
        $d1 = self::orientation($q1, $q2, $p1);
        $d2 = self::orientation($q1, $q2, $p2);
        $d3 = self::orientation($p1, $p2, $q1);
        $d4 = self::orientation($p1, $p2, $q2);

        return (($d1 > 0 && $d2 < 0) || ($d1 < 0 && $d2 > 0))
            && (($d3 > 0 && $d4 < 0) || ($d3 < 0 && $d4 > 0));
    }

    private static function orientation(array $a, array $b, array $c): float
    {
        return ($b[1] - $a[1]) * ($c[0] - $a[0]) - ($b[0] - $a[0]) * ($c[1] - $a[1]);
    }
}
