{{-- Dashboard "Field conditions" card body, loaded by the dashboard after the page (FieldWeatherService). --}}
@if($forecast === null)
<div class="field-empty">
    <i class="bi bi-cloud-slash"></i>
    <span>The weather forecast is unavailable right now. Check PAGASA before going out to the field.</span>
</div>
@else
@php
    $today = $forecast['days'][0] ?? null;
    $current = $forecast['current'];
@endphp
<div class="field-body">
    <div class="field-now">
        <i class="bi {{ $current['icon'] }} field-now-icon" aria-hidden="true"></i>
        <div>
            <div class="field-now-temp">{{ $current['temperature'] ?? '—' }}°C</div>
            <div class="field-now-desc">{{ $current['description'] }}</div>
            <div class="field-now-meta">
                Wind {{ $current['wind'] ?? '—' }} km/h
                @if($current['gusts'])
                · gusts {{ $current['gusts'] }} km/h
                @endif
                @if($current['humidity'] !== null)
                · {{ $current['humidity'] }}% humidity
                @endif
            </div>
            @if($today)
            <div class="field-now-meta">
                Today {{ $today['temp_max'] ?? '—' }}° / {{ $today['temp_min'] ?? '—' }}°
                · <i class="bi bi-droplet" aria-hidden="true"></i> {{ $today['rain_chance'] ?? 0 }}% rain
            </div>
            @endif
        </div>
        @if($today)
        <div class="field-verdict is-{{ $today['rating'] }}">
            <i class="bi {{ $today['rating'] === 'good' ? 'bi-check-circle-fill' : ($today['rating'] === 'caution' ? 'bi-exclamation-circle-fill' : 'bi-x-octagon-fill') }}" aria-hidden="true"></i>
            <div>
                <strong>{{ $today['rating_label'] }} today</strong>
                <span>{{ $today['rating_reason'] }}</span>
            </div>
        </div>
        @endif
    </div>
</div>
<div class="field-foot">
    @if(!empty($forecast['is_current_location']))
    <i class="bi bi-geo-alt-fill" aria-hidden="true"></i> Near you: {{ $forecast['place'] }}
    @else
    <i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $forecast['place'] }} <span class="field-foot-hint">(allow location access to see your area)</span>
    @endif
    · Forecast updated {{ \Illuminate\Support\Carbon::parse($forecast['updated_at'])->diffForHumans() }}.
    Always follow PAGASA advisories. Weather data by <a href="https://open-meteo.com/" target="_blank" rel="noopener">Open-Meteo.com</a>
</div>
@endif
