@php
    /** @var array<int, array{name: string, description: string, version: string|null, slug: string}> $modules */
@endphp

<x-app.layout :title="config('app.name') . ' — Dashboard'" :modules="$modules">
    <div class="grid gap-6">
        <section class="card">
            <h2 class="card-title">Moduli attivi</h2>
            <p class="card-description">
                Elenco dei moduli attivi nell’app.
            </p>

            @if (count($modules) === 0)
                <div class="mt-4 p-4 text-sm text-gray-500">
                    <div class="font-semibold">Nessun modulo attivo trovato.</div>
                    <div class="mt-1 text-xs text-gray-500">Controlla <code class="rounded bg-gray-100 px-1 py-0.5 dark:bg-gray-900">modules_statuses.json</code>.</div>
                </div>
            @else
                @foreach ($modules as $module)
                    <div class="mt-4 rounded-2xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900/30 dark:text-zinc-200">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 grow">
                                <div class="flex items-center justify-between gap-2 px-2 py-1">
                                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $module['name'] }}
                                    </h3>
                                    @if (! empty($module['version']))
                                        <span class="text-xs text-gray-600 dark:text-gray-400">
                                            {{ $module['version'] }}
                                        </span>
                                    @endif
                                </div>
                                
                                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400 p-2">
                                    {{ $module['description'] !== '' ? $module['description'] : 'Nessuna descrizione disponibile.' }}
                                </div>
                            </div>

                            <a
                                class="btn border border-white/20 bg-white/5 hover:bg-white/10"
                                href="{{ route('app.module', ['module' => $module['name']]) }}"
                            >
                                Apri
                            </a>
                        </div>
                    </div>
                @endforeach
            @endif
        </section>
    </div>
</x-app.layout>

