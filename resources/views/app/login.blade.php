@php
    /** @var array<int, array{name: string, description: string, version: string|null, slug: string}> $modules */
@endphp

<x-app.layout :title="config('app.name') . ' — Login'" :modules="$modules">
    <div class="max-w-md mx-auto">
        <section class="card">
            <h1 class="card-title">Login</h1>
            <p class="card-description">
                Accedi con il tuo account.
            </p>

            @if ($errors->any())
                <div class="mt-4 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-900/40 dark:bg-red-800/30 dark:text-red-200">
                    <div class="font-semibold">Errore</div>
                    <div class="mt-2 space-y-1">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="mt-4 space-y-4">
                @csrf

                <div>
                    <label class="form-label" for="email">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        autocomplete="username"
                        required
                        value="{{ old('email') }}"
                        class="form-field"
                    >
                </div>

                <div>
                    <label class="form-label" for="password">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="form-field"
                    >
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <input
                        type="checkbox"
                        name="remember"
                        class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-200 dark:border-gray-700 dark:bg-white/15 dark:focus:ring-emerald-800/40"
                    >
                    Remember me
                </label>

                <button type="submit" class="btn btn-primary w-full">
                    Entra
                </button>
            </form>
        </section>
    </div>
</x-app.layout>

