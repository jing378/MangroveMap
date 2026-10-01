@extends('layouts.enduser')

@section('title', 'Delineate Mangrove Zones - MangroveMap')

@section('content-class', 'content-flush')

@section('styles')
    @include('users.partials.workspace-styles')
    <style>
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
    </style>
@endsection

@section('content')
    @include('users.partials.workspace-map')
@endsection

@section('scripts')
    <script>
        document.body.setAttribute('data-page', 'delineate');
    </script>
    @include('users.partials.workspace-scripts')
@endsection
