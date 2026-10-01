@extends('layouts.enduser')

@section('title', 'Mangrove Coverage Map - MangroveMap')

@section('content-class', 'content-flush')

@section('styles')
    @include('users.partials.workspace-styles')
    <style>
        /* Map-only full viewport layout */
        .content.content-flush {
            position: relative;
            width: 100%;
            height: calc(100vh - 60px);
            overflow: hidden !important;
        }
        .enduser-workspace {
            width: 100%;
            height: 100%;
        }
        /* Completely hide left summary panel so only the map is displayed */
        .left-panel {
            display: none !important;
            width: 0 !important;
            min-width: 0 !important;
            max-width: 0 !important;
            padding: 0 !important;
            border: none !important;
        }
        .main {
            width: 100% !important;
            flex: 1 !important;
        }
        .map-wrap {
            width: 100% !important;
            height: 100% !important;
        }
        #mainMap {
            width: 100% !important;
            height: 100% !important;
        }
    </style>
@endsection

@section('content')
    @include('users.partials.workspace-map')
@endsection

@section('scripts')
    <script>
        document.body.setAttribute('data-page', 'map');
        // Ensure map recalculates full width immediately after render
        window.addEventListener('DOMContentLoaded', () => {
            if (typeof mainMap !== 'undefined' && mainMap) {
                setTimeout(() => mainMap.invalidateSize(), 50);
                setTimeout(() => mainMap.invalidateSize(), 200);
            }
        });
    </script>
    @include('users.partials.workspace-scripts')
@endsection
