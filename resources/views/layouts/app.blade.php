<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="theme-color" content="#1e9e62">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="manifest" href="/manifest.json">
  <title>@yield('title', config('app.name', 'Capstone'))</title>

  {{-- Page-specific styles --}}
  @yield('styles')
  @stack('styles')
</head>

<body>
  @yield('content')

  {{-- Page-specific scripts --}}
  @yield('scripts')
  @stack('scripts')
</body>

</html>