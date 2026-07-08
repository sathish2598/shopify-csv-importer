<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Shopify CSV Importer')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 text-gray-900 antialiased">
    <nav class="bg-gray-900 text-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <a href="{{ route('uploads.create') }}" class="text-lg font-semibold tracking-tight">
                Shopify CSV Importer
            </a>
            <div class="flex items-center gap-1 text-sm">
                @foreach ([
                    'uploads.create' => 'Upload',
                    'dashboard.index' => 'Dashboard',
                    'logs.index' => 'Logs',
                ] as $routeName => $label)
                    <a href="{{ route($routeName) }}"
                       class="rounded-md px-3 py-2 transition {{ request()->routeIs(str_replace('.index', '.*', $routeName)) || request()->routeIs($routeName) ? 'bg-gray-700 font-medium' : 'hover:bg-gray-800' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-6xl px-4 py-8">
        @if (session('success'))
            <div class="mb-6 rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
