@extends('layouts.enduser')

@section('title', 'Image Delineation - MangroveMap')

@section('styles')
    @include('users.partials.upload-styles')
    <style>
        .page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 8px 0 40px;
        }
    </style>
@endsection

@section('content')
    @include('users.partials.upload-body')
@endsection

@section('scripts')
    @include('users.partials.upload-scripts')
@endsection
