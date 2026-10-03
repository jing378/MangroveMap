@extends('layouts.admin')

@section('title', 'Annual Mangrove Observations')

@section('content')
    <style>
        .observation-page { max-width: 1100px; margin: 0 auto; }
        .observation-heading { margin-bottom: 22px; }
        .observation-heading h1 { font-size: 24px; color: #1a2e1a; }
        .observation-heading p { margin-top: 5px; color: #6a8a6a; font-size: 13px; }
        .observation-form, .observation-table-wrap { background: #fff; border: 1px solid #e0e8e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .observation-form h2, .observation-table-wrap h2 { font-size: 16px; margin-bottom: 16px; color: #1a2e1a; }
        .observation-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .observation-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .observation-field label { font-size: 12px; font-weight: 700; color: #3a5a3a; }
        .observation-field input, .observation-field select { width: 100%; min-height: 40px; border: 1px solid #d4e0d4; border-radius: 6px; padding: 8px 10px; color: #1a2e1a; background: #fff; font: inherit; font-size: 13px; }
        .observation-genus { grid-column: 1 / -1; }
        .observation-genus-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 10px; }
        .observation-help { font-size: 11px; color: #6a8a6a; }
        .observation-actions { display: flex; gap: 10px; margin-top: 16px; }
        .observation-button { display: inline-flex; align-items: center; gap: 7px; border: 1px solid #1e9e62; border-radius: 6px; background: #1e9e62; color: #fff; padding: 10px 14px; font: inherit; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; }
        .observation-button.secondary { background: #fff; color: #3a5a3a; border-color: #d4e0d4; }
        .observation-alert { border-radius: 6px; padding: 12px 14px; margin-bottom: 16px; font-size: 13px; }
        .observation-alert.success { background: #edf7f2; color: #176b42; }
        .observation-alert.error { background: #fdf0ee; color: #a52f21; }
        .observation-alert ul { padding-left: 18px; }
        .observation-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .observation-table th, .observation-table td { text-align: left; border-bottom: 1px solid #edf2ed; padding: 10px 8px; vertical-align: top; }
        .observation-table th { color: #6a8a6a; font-size: 10px; text-transform: uppercase; }
        .observation-edit { color: #176b42; font-weight: 700; text-decoration: none; }
        .observation-empty { color: #6a8a6a; font-size: 13px; padding: 12px 0; }
        @media (max-width: 700px) {
            .observation-grid, .observation-genus-grid { grid-template-columns: 1fr; }
            .observation-genus { grid-column: auto; }
            .observation-table-wrap { overflow-x: auto; }
        }
    </style>

    <div class="observation-page">
        <div class="observation-heading">
            <h1>Annual Observations</h1>
            <p>Record verified coverage measurements and genus composition for a delineated zone.</p>
        </div>

        @if (session('success'))
            <div class="observation-alert success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="observation-alert error">
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="observation-form">
            <h2>{{ $editingObservation ? 'Edit annual measurement' : 'Add annual measurement' }}</h2>
            <form method="POST" action="{{ route('admin.annual-observations.store') }}">
                @csrf
                @if ($editingObservation)
                    <input type="hidden" name="observation_id" value="{{ $editingObservation->id }}">
                @endif
                <div class="observation-grid">
                    <div class="observation-field">
                        <label for="delineation_id">Delineated zone</label>
                        <select id="delineation_id" name="delineation_id" required>
                            <option value="">Select a zone</option>
                            @foreach ($delineations as $delineation)
                                <option value="{{ $delineation->id }}" @selected((string) old('delineation_id', $editingObservation?->delineation_id) === (string) $delineation->id)>
                                    {{ $delineation->name ?: 'Delineation #' . $delineation->id }}{{ $delineation->user ? ' - ' . $delineation->user->name : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="observation-field">
                        <label for="year">Observation year</label>
                        <input id="year" name="year" type="number" min="2000" max="{{ now()->year }}" required
                            value="{{ old('year', $editingObservation?->year ?? now()->year) }}">
                    </div>
                    <div class="observation-field">
                        <label for="observation_date">Survey date</label>
                        <input id="observation_date" name="observation_date" type="date" required
                            value="{{ old('observation_date', $editingObservation?->observation_date?->format('Y-m-d')) }}">
                    </div>
                    <div class="observation-field">
                        <label for="coverage_area_ha">Mangrove coverage (hectares)</label>
                        <input id="coverage_area_ha" name="coverage_area_ha" type="number" min="0" step="0.0001" required
                            value="{{ old('coverage_area_ha', $editingObservation?->coverage_area_ha) }}">
                    </div>
                    <div class="observation-field" style="grid-column:1/-1;">
                        <label for="source">Measurement source</label>
                        <input id="source" name="source" type="text" maxlength="255" required
                            placeholder="Satellite/survey source, scene ID, or report reference"
                            value="{{ old('source', $editingObservation?->source) }}">
                    </div>
                    <div class="observation-field observation-genus">
                        <label>Genus distribution (optional)</label>
                        <div class="observation-help">Enter measured percentages from the same survey. Provided values must total 100%.</div>
                        @if ($genera->isEmpty())
                            <div class="observation-help">Add genera in dataset management before entering genus shares.</div>
                        @else
                            <div class="observation-genus-grid">
                                @foreach ($genera as $genus)
                                    <div class="observation-field">
                                        <label for="genus-{{ $genus->id }}">{{ $genus->common_name }} (%)</label>
                                        <input id="genus-{{ $genus->id }}" name="genus_distribution[{{ $genus->id }}]" type="number" min="0" max="100" step="0.01"
                                            value="{{ old('genus_distribution.' . $genus->id, $editingObservation?->genus_distribution[$genus->id] ?? '') }}">
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                <div class="observation-actions">
                    <button class="observation-button" type="submit"><i class="bi bi-save"></i> Save measurement</button>
                    @if ($editingObservation)
                        <a class="observation-button secondary" href="{{ route('admin.annual-observations.create') }}">Cancel</a>
                    @endif
                </div>
            </form>
        </section>

        <section class="observation-table-wrap">
            <h2>Recorded measurements</h2>
            @if ($observations->isEmpty())
                <p class="observation-empty">No annual observations have been entered yet.</p>
            @else
                <table class="observation-table">
                    <thead><tr><th>Zone</th><th>Year</th><th>Survey date</th><th>Coverage</th><th>Source</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($observations as $observation)
                            <tr>
                                <td>{{ $observation->delineation?->name ?: 'Delineation #' . $observation->delineation_id }}</td>
                                <td>{{ $observation->year }}</td>
                                <td>{{ $observation->observation_date->format('Y-m-d') }}</td>
                                <td>{{ number_format($observation->coverage_area_ha, 2) }} ha</td>
                                <td>{{ $observation->source }}</td>
                                <td><a class="observation-edit" href="{{ route('admin.annual-observations.create', ['observation' => $observation->id]) }}">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="margin-top:14px">{{ $observations->links() }}</div>
            @endif
        </section>
    </div>
@endsection