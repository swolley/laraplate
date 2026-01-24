<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

        <script>
            (function() {
                const theme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            })();
        </script>
    </head>
    <body class="bg-white font-sans text-neutral-900 antialiased dark:bg-neutral-900 dark:text-neutral-100">
        <header class="sticky top-0 z-99 mx-auto h-fit max-w-[1400px] bg-white px-4 py-8 transition-transform duration-300 xl:px-16 dark:bg-neutral-900 -translate-y-4 pb-4">
            <div class="grid grid-cols-12 gap-4 lg:gap-6 xl:gap-x-10 relative h-full items-center overflow-hidden">
                <ul class="col-span-3 flex items-start space-x-8 font-medium">
                    <li class="mr-18 transition-transform duration-300 ease-in-out">
                        <a href="{{ route('app.home') }}" class="flex items-center gap-3 h-10">
                            <img src="{{ config('app.logo') }}" alt="Logo" class="h-full w-full" onerror="this.onerror=null; this.style.display='none'; this.style.visibility='hidden'">
                            <span class="text-lg font-demibold tracking-tight">{{ config('app.name') }}</span>
                        </a>
                    </li>
                </ul>

                <nav class="dark:text-sand-dark-12 col-span-9 flex items-center justify-end gap-4 lg:col-span-9" aria-label="Modules">
                    @if (auth('web')->check())
                        @php
                            $links = [
                                [
                                    'route' => 'app.home',
                                    'text' => 'Home',
                                    'params' => [],
                                    'selected' => request()->routeIs('app.home'),
                                ],
                                ... array_map(function ($nav_module) {
                                        return [
                                            'route' => 'app.module',
                                            'text' => $nav_module['name'],
                                            'params' => ['module' => $nav_module['name']],
                                            'selected' => request()->routeIs('app.module') && strcasecmp((string) request()->route('module'), (string) $nav_module['name']) === 0,
                                        ];
                                    }, $modules ?? []),
                            ];
                        @endphp

                        @foreach ($links as $link)
                            <a
                                class="inline-flex items-center px-3 py-1 text-sm transition hover:text-neutral-500 dark:hover:text-neutral-500 @if ($link['selected']) text-emerald-900 dark:text-emerald-200 @endif"
                                href="{{ route($link['route'], $link['params']) }}"
                            >
                                {{ $link['text'] }}
                            </a>
                        @endforeach
                    @endif

                    @if (auth('web')->check())
                        @php
                            $user = auth('web')->user();
                            $initial = strtoupper(substr($user->email, 0, 1));
                        @endphp

                        <div
                            x-data="{
                                open: false,
                                isDark: document.documentElement.classList.contains('dark'),
                                toggleTheme() {
                                    this.isDark = !this.isDark;
                                    if (this.isDark) {
                                        document.documentElement.classList.add('dark');
                                        localStorage.setItem('theme', 'dark');
                                    } else {
                                        document.documentElement.classList.remove('dark');
                                        localStorage.setItem('theme', 'light');
                                    }
                                }
                            }"
                            class="relative"
                        >
                            <button
                                @click="open = !open"
                                class="flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium text-gray-700 transition dark:text-gray-200 cursor-pointer"
                            >
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-semibold text-white uppercase">
                                    {{ $initial }}
                                </span>
                            </button>

                            <div
                                x-show="open"
                                @click.away="open = false"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
                                style="display: none;"
                            >
                                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-800">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $user->name ?? $user->email }}</p>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                                </div>

                                <div class="p-1">
                                    <button
                                        type="button"
                                        @click="toggleTheme()"
                                        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                                    >
                                        <svg x-show="!isDark" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                                        </svg>
                                        <svg x-show="isDark" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                        </svg>
                                        <span x-text="isDark ? 'Light mode' : 'Dark mode'">Dark mode</span>
                                    </button>

                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                            </svg>
                                            Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            @if (! (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))))
                <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200">
                    Asset non compilati: avvia <code class="rounded bg-amber-100 px-1 py-0.5 dark:bg-amber-900/30">npm run dev</code> oppure <code class="rounded bg-amber-100 px-1 py-0.5 dark:bg-amber-900/30">npm run build</code>.
                </div>
            @endif

            {{ $slot }}
        </main>
    </body>
</html>

