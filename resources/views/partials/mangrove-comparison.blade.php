{{--
    "Compared with official figures": MangroveMap's expert-approved hectares beside the official totals
    (config/mangrove_reference.php) and the satellite (App\Services\MangroveComparison). Kept short: three bars on
    one scale (provincial figure, satellite, MangroveMap) and two one-line checks; the PSA national and regional
    context, the municipality table, the surveys behind the AI score and the explanation sit in "More details".

    Parameters: $comparison (MangroveComparison::summary()), $cardClass (the page's card class, for its
    background and spacing), $showSurveys (list the surveys behind the AI score: experts and admins),
    $mapUrl (the viewer's map page).
--}}
@php
    $cmp = $comparison;
    $sat = $cmp['satellite'];
    $acc = $cmp['accuracy'];
    $agree = $cmp['agreement'];
    $province = $cmp['province'];
    $cmpHa = fn ($ha) => number_format((float) $ha, $ha < 100 ? 2 : ($ha < 10000 ? 1 : 0));
    // Official figures as published: up to 2 decimals, trailing zeros dropped (311,216 / 942.77 / 449.6).
    $cmpExact = fn ($ha) => rtrim(rtrim(number_format((float) $ha, 2), '0'), '.');
    $cmpPct = fn ($share) => $share === null ? '—' : number_format($share * 100, $share * 100 < 10 ? 1 : 0).'%';
    $cmpScore = fn ($v) => $v === null ? '—' : number_format($v, 2);
    $cmpPlaces = collect($cmp['municipalities']);
    $cmpMapUrl = $mapUrl ?? route('map');
    $context = collect($cmp['references'])->reject(fn ($figure) => $figure['key'] === ($province['key'] ?? null));

    // The three bars, on the scale of the largest.
    $bars = array_values(array_filter([
        $province ? [
            'label' => ($province['place'] ?? 'Province').', official',
            'ha' => (float) $province['hectares'],
            'value' => $cmpExact($province['hectares']),
            'source' => $province['source'].', '.$province['year'],
            'url' => $province['url'] ?? null,
            'class' => 'is-official',
        ] : null,
        $sat ? [
            'label' => 'Satellite (previous data)',
            'ha' => (float) $sat['latest_hectares'],
            'value' => $cmpExact($sat['latest_hectares']),
            'source' => 'Global Mangrove Watch, '.$sat['latest_year'],
            'url' => $sat['source_url'],
            'class' => 'is-satellite',
        ] : null,
        [
            'label' => 'MangroveMap',
            'ha' => (float) $cmp['system']['area_ha'],
            'value' => $cmpHa($cmp['system']['area_ha']),
            'source' => number_format($cmp['system']['area_count']).' expert-approved '.Str::plural('area', $cmp['system']['area_count']),
            'url' => null,
            'class' => 'is-own',
        ],
    ]));
    $barMax = max(0.01, ...array_column($bars, 'ha'));
@endphp

<section class="cmp-card {{ $cardClass ?? '' }}" id="officialComparison" aria-labelledby="officialComparisonTitle">
    <div class="cmp-head">
        <div>
            <h2 class="cmp-title" id="officialComparisonTitle"><i class="bi bi-patch-check"></i> Compared with official figures</h2>
            <p class="cmp-sub">
                @if($cmp['system']['share_of_province'] !== null)
                    MangroveMap has mapped <strong>{{ number_format($cmp['system']['share_of_province'], $cmp['system']['share_of_province'] < 10 ? 1 : 0) }}%</strong>
                    of {{ $province['place'] }}'s official mangrove area so far.
                @else
                    MangroveMap's mapped area next to official and satellite figures.
                @endif
            </p>
        </div>
        {{-- The map's "Mangroves mapped by year" card with "Compare with previous data" on (map-timeline-scripts). --}}
        <a class="cmp-map-link" href="{{ $cmpMapUrl }}?compare=previous"><i class="bi bi-map"></i> Compare on the map</a>
    </div>

    {{-- 1. Three bars on one scale. --}}
    <ul class="cmp-bars" aria-label="Mangrove area in hectares">
        @foreach($bars as $bar)
            <li class="cmp-bar-row {{ $bar['class'] }}">
                <span class="cmp-bar-label">
                    {{ $bar['label'] }}
                    <small>@if($bar['url'])<a href="{{ $bar['url'] }}" target="_blank" rel="noopener" data-no-progress>{{ $bar['source'] }}</a>@else{{ $bar['source'] }}@endif</small>
                </span>
                <span class="cmp-bar-track" aria-hidden="true">
                    <span class="cmp-bar-fill" style="width: {{ $bar['ha'] > 0 ? max(0.8, round($bar['ha'] / $barMax * 100, 1)) : 0 }}%"></span>
                </span>
                <span class="cmp-bar-value">{{ $bar['value'] }} <small>ha</small></span>
            </li>
        @endforeach
    </ul>

    {{-- 2. Two checks, one line each. --}}
    <div class="cmp-chips">
        <div class="cmp-chip" title="Share of the approved areas that the satellite also recorded as mangrove. Small or newly planted stands are below its 30 m detail, so this is expected to be low.">
            <i class="bi bi-globe-asia-australia" aria-hidden="true"></i>
            <span class="cmp-chip-label">Satellite agreement</span>
            <strong>{{ $agree ? $cmpPct($agree['agreement']) : '—' }}</strong>
        </div>
        <div class="cmp-chip" title="How closely the AI's mangrove areas matched the boundaries people reviewed (F1 score; 1.00 is a perfect match).">
            <i class="bi bi-cpu" aria-hidden="true"></i>
            <span class="cmp-chip-label">AI accuracy</span>
            @if($acc['count'] > 0)
                <strong>{{ $cmpScore($acc['pooled']['f1']) }}</strong>
                <span class="cmp-chip-note">F1 · {{ $acc['count'] }} {{ Str::plural('survey', $acc['count']) }}</span>
            @else
                <span class="cmp-chip-note">no reviewed drone surveys yet</span>
            @endif
        </div>
    </div>

    {{-- 3. Everything else, closed by default. --}}
    <details class="cmp-more">
        <summary>More details</summary>

        @if($context->isNotEmpty())
            <p class="cmp-context">
                For context:
                @foreach($context as $figure)
                    {{ $figure['place'] }} <strong>{{ $cmpExact($figure['hectares']) }} ha</strong>@if(!$loop->last) · @endif
                @endforeach
                <span class="cmp-muted">
                    (@if(!empty($context->first()['url']))<a href="{{ $context->first()['url'] }}" target="_blank" rel="noopener" data-no-progress>{{ $context->first()['source'] }}</a>@else{{ $context->first()['source'] }}@endif, {{ $context->first()['year'] }})
                </span>
            </p>
        @endif

        @if($cmpPlaces->isNotEmpty())
            <div class="cmp-table-wrap">
                <table class="cmp-table">
                    <caption class="cmp-caption">By municipality</caption>
                    <thead>
                        <tr>
                            <th scope="col">Municipality</th>
                            @if($sat)<th scope="col" class="cmp-num">Satellite {{ $sat['latest_year'] }}</th>@endif
                            <th scope="col" class="cmp-num">MangroveMap</th>
                            @if($sat)<th scope="col" class="cmp-num" title="MangroveMap hectares as a share of the satellite's figure">Mapped</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cmpPlaces as $row)
                            <tr>
                                <th scope="row">
                                    <a class="cmp-place-link" href="{{ $cmpMapUrl }}?compare=previous&amp;municipality={{ urlencode($row['name']) }}" title="Compare {{ $row['name'] }} on the map">{{ $row['name'] }}</a>
                                </th>
                                @if($sat)<td class="cmp-num">{{ $cmpHa($row['satellite_latest_ha']) }}</td>@endif
                                <td class="cmp-num">{{ $row['system_ha'] > 0 ? $cmpHa($row['system_ha']) : '—' }}</td>
                                @if($sat)
                                    <td class="cmp-num">
                                        @if($row['mapped_percent'] === null)
                                            {{ $row['system_ha'] > 0 ? 'not on satellite' : '—' }}
                                        @else
                                            {{ number_format($row['mapped_percent'], $row['mapped_percent'] < 10 ? 1 : 0) }}%
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if(!empty($showSurveys) && $acc['count'] > 0)
            <div class="cmp-table-wrap">
                <table class="cmp-table">
                    <caption class="cmp-caption">Drone surveys behind the AI accuracy</caption>
                    <thead>
                        <tr>
                            <th scope="col">Survey</th>
                            <th scope="col">Checked against</th>
                            <th scope="col" class="cmp-num">AI ha</th>
                            <th scope="col" class="cmp-num">Final ha</th>
                            <th scope="col" class="cmp-num">F1</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($acc['cases'] as $case)
                            <tr>
                                <th scope="row">{{ $case['layer'] }}</th>
                                <td>{{ $case['reference_label'] }}</td>
                                <td class="cmp-num">{{ $cmpHa($case['predicted_ha']) }}</td>
                                <td class="cmp-num">{{ $cmpHa($case['reference_ha']) }}</td>
                                <td class="cmp-num">{{ $cmpScore($case['f1']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="cmp-foot">
            The figures measure different things: the official totals are inventories, the satellite sees mangroves at about
            30 m, and MangroveMap counts only areas surveyed and approved by experts here, so its total grows as areas are surveyed.
        </p>
    </details>
</section>

@once
<style>
    .cmp-card { display: flex; flex-direction: column; gap: 16px; }
    .cmp-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
    .cmp-title { margin: 0; font-size: 16px; font-weight: 800; color: var(--fg-1a2e1a, #1a2e1a); display: flex; align-items: center; gap: 8px; }
    .cmp-title i { color: var(--fg-1e9e62, #1e9e62); }
    .cmp-sub { margin: 4px 0 0; font-size: 13px; color: var(--fg-4a6a4a, #4a6a4a); line-height: 1.45; }
    .cmp-sub strong { color: var(--fg-1e9e62, #1e9e62); }
    .cmp-map-link { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 10px; border: 1px solid var(--bd-b8d9c6, #b8d9c6); background: var(--bg-edf7f2, #edf7f2); color: var(--fg-1e9e62, #1e9e62); font-size: 13px; font-weight: 700; text-decoration: none; white-space: nowrap; }
    .cmp-map-link:hover { background: var(--bg-dff0e7, #dff0e7); }

    /* Three bars on one scale. */
    .cmp-bars { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
    .cmp-bar-row { display: grid; grid-template-columns: minmax(150px, 30%) 1fr auto; align-items: center; gap: 12px; }
    .cmp-bar-label { display: flex; flex-direction: column; font-size: 13px; font-weight: 700; color: var(--fg-1a2e1a, #1a2e1a); min-width: 0; }
    .cmp-bar-label small { font-size: 11px; font-weight: 600; color: var(--fg-6a8a6a, #6a8a6a); overflow-wrap: anywhere; }
    .cmp-bar-label small a { color: var(--fg-6a8a6a, #6a8a6a); text-decoration: underline; text-decoration-color: #c4d4c4; }
    .cmp-bar-track { height: 10px; border-radius: 6px; background: var(--bg-edf2ed, #edf2ed); overflow: hidden; }
    .cmp-bar-fill { display: block; height: 100%; border-radius: 6px; }
    .cmp-bar-row.is-official .cmp-bar-fill { background: #7a9a8a; }
    .cmp-bar-row.is-satellite .cmp-bar-fill { background: #c98f00; }
    .cmp-bar-row.is-own .cmp-bar-fill { background: #1e9e62; }
    .cmp-bar-value { font-size: 15px; font-weight: 800; color: var(--fg-1a2e1a, #1a2e1a); white-space: nowrap; font-variant-numeric: tabular-nums; text-align: right; min-width: 92px; }
    .cmp-bar-value small { font-size: 11px; color: var(--fg-6a8a6a, #6a8a6a); }
    .cmp-bar-row.is-own .cmp-bar-value { color: var(--fg-1e9e62, #1e9e62); }

    /* Two one-line checks. */
    .cmp-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .cmp-chip { display: inline-flex; align-items: baseline; gap: 6px; padding: 7px 12px; border-radius: 999px; background: var(--bg-f5f7f6, #f5f7f6); border: 1px solid var(--bd-e0e8e0, #e0e8e0); font-size: 12.5px; color: var(--fg-4a6a4a, #4a6a4a); cursor: help; }
    .cmp-chip i { color: var(--fg-1e9e62, #1e9e62); align-self: center; }
    .cmp-chip strong { font-size: 14px; color: var(--fg-1a2e1a, #1a2e1a); }
    .cmp-chip-note { font-size: 11.5px; color: var(--fg-6a8a6a, #6a8a6a); }

    /* More details. */
    .cmp-more { border-top: 1px solid var(--bd-edf2ed, #edf2ed); padding-top: 10px; }
    .cmp-more summary { cursor: pointer; font-size: 13px; font-weight: 700; color: var(--fg-1e9e62, #1e9e62); }
    .cmp-more[open] { display: flex; flex-direction: column; gap: 14px; }
    .cmp-more[open] summary { margin-bottom: 2px; }
    .cmp-context { margin: 0; font-size: 12.5px; color: var(--fg-4a6a4a, #4a6a4a); line-height: 1.5; }
    .cmp-context strong { color: var(--fg-1a2e1a, #1a2e1a); }
    .cmp-muted, .cmp-muted a { color: var(--fg-6a8a6a, #6a8a6a); }
    .cmp-table-wrap { overflow-x: auto; }
    .cmp-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .cmp-caption { caption-side: top; text-align: left; font-size: 12.5px; font-weight: 800; color: var(--fg-1a2e1a, #1a2e1a); padding-bottom: 6px; }
    .cmp-table th, .cmp-table td { padding: 7px 10px; border-bottom: 1px solid var(--bd-edf2ed, #edf2ed); text-align: left; vertical-align: middle; color: var(--fg-1a2e1a, #1a2e1a); }
    .cmp-table thead th { font-size: 11.5px; font-weight: 700; color: var(--fg-6a8a6a, #6a8a6a); white-space: nowrap; }
    .cmp-table tbody th { font-weight: 700; }
    .cmp-num { text-align: right !important; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .cmp-place-link { font-weight: 700; color: var(--fg-1a2e1a, #1a2e1a); text-decoration: none; }
    .cmp-place-link:hover, .cmp-place-link:focus-visible { color: var(--fg-1e9e62, #1e9e62); text-decoration: underline; }
    .cmp-foot { margin: 0; font-size: 12px; color: var(--fg-6a8a6a, #6a8a6a); line-height: 1.5; }

    @media (max-width: 720px) {
        .cmp-bar-row { grid-template-columns: 1fr auto; row-gap: 4px; }
        .cmp-bar-track { grid-column: 1 / -1; grid-row: 2; }
        .cmp-bar-value { min-width: 0; }
        .cmp-table th, .cmp-table td { padding: 7px 6px; }
    }
</style>
@endonce
