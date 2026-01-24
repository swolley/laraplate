@php
    /** @var array<int, array{name: string, description: string, version: string|null, slug: string}> $modules */
    /** @var array{name: string, description: string, version: string|null, slug: string} $module */
    /** @var array<int, array{fqcn: string, label: string, key: string, resource: string}> $models */
@endphp

<x-app.layout :title="config('app.name') . ' — ' . $module['name']" :modules="$modules">
    <div class="grid gap-6 lg:grid-cols-3">
        <aside class="card">
            <h2 class="card-title">Modelli</h2>

            @if (count($models) === 0)
                <div class="mt-4 p-4 text-sm text-gray-500">
                    <div class="font-semibold">Nessun modello trovato per questo modulo.</div>
                </div>
            @else
                <div class="mt-4 space-y-3">
                    @foreach ($models as $model)
                        <div class="border-b border-white/70 p-4 dark:border-gray-800">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 grow">
                                    <div class="flex items-center justify-between gap-2 rounded bg-gray-50 px-2 py-1 dark:bg-white/5">
                                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $model['label'] }}
                                        </h3>
                                        @if (! empty($model['version']))
                                            <span class="text-xs text-gray-600 dark:text-gray-400">
                                                {{ $model['version'] }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-400 p-2">
                                        {{ $model['description'] !== '' ? $model['description'] : 'Nessuna descrizione disponibile.' }}
                                    </div>
                                </div>

                                <a
                                    class="btn border border-white/20 bg-white/5 hover:bg-white/10"
                                    href="{{ route('app.model', ['module' => $module['name'], 'model' => $model['key']]) }}"
                                >
                                    Apri
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </aside>

        <section class="card lg:col-span-2">
            <h3 class="card-title">{{ $module['name'] }}</h3>
            <p class="card-description">
                {{ $module['description'] !== '' ? $module['description'] : 'Nessuna descrizione disponibile.' }}
            </p>

            <div class="mt-4 rounded-2xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900/30 dark:text-zinc-200">
                <div class="font-semibold text-zinc-900 dark:text-zinc-100">Panoramica</div>
                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    Qui possiamo aggiungere funzionalità specifiche del modulo man mano che l’app cresce.
                </div>
            </div>
        </section>
    </div>
</x-app.layout>

