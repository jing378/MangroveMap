<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Mangrove Monitoring')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src='https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js'></script>
    <link href='https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css' rel='stylesheet' />
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation Bar (Public) -->
    <nav class="bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center sticky top-0 z-50">
        <div class="flex items-center space-x-2">
            <span class="text-3xl">🥭</span>
            <div>
                <h1 class="text-xl font-bold text-green-700">Mangrove Monitor</h1>
                <p class="text-xs text-gray-500">Global Ecosystem Tracking</p>
            </div>
        </div>
        
        <div class="flex items-center space-x-4">
            @guest
                <a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-900 font-medium">Login</a>
                <a href="{{ route('register') }}" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    Sign Up
                </a>
            @else
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700">{{ auth()->user()->name }}</span>
                    <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-900">Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button class="text-gray-600 hover:text-gray-900">Logout</button>
                    </form>
                </div>
            @endguest
        </div>
    </nav>
    
    <!-- Main Content -->
    <main>
        @yield('content')
    </main>
    
    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12 mt-12">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid grid-cols-3 gap-8 mb-8">
                <div>
                    <h3 class="text-lg font-semibold mb-4">About</h3>
                    <p class="text-gray-400 text-sm">Monitoring and protecting mangrove ecosystems worldwide through AI and data-driven insights.</p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Features</h3>
                    <ul class="text-gray-400 text-sm space-y-2">
                        <li><a href="#" class="hover:text-white">Interactive Mapping</a></li>
                        <li><a href="#" class="hover:text-white">AI Analysis</a></li>
                        <li><a href="#" class="hover:text-white">Sustainability Reports</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Contact</h3>
                    <p class="text-gray-400 text-sm">contact@mangrovemonitor.com</p>
                </div>
            </div>
            <div class="border-t border-gray-700 pt-6 text-center text-gray-400 text-sm">
                <p>&copy; 2024 Mangrove Monitor. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>