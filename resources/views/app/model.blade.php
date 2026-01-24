@php
    /** @var array<int, array{name: string, description: string, version: string|null, slug: string}> $modules */
    /** @var array{name: string, description: string, version: string|null, slug: string} $module */
    /** @var array<int, array{fqcn: string, label: string, key: string, resource: string}> $models */
    /** @var array{fqcn: string, label: string, key: string, resource: string} $model */
@endphp

<x-app.layout :title="config('app.name') . ' — ' . $module['name'] . ' — ' . $model['label']" :modules="$modules">
    <div class="grid gap-6 lg:grid-cols-3">
        <aside class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 lg:col-span-1">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Modelli</h2>

            @if (count($models) === 0)
                <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    Nessun modello trovato per questo modulo.
                </div>
            @else
                <div class="mt-3 space-y-2">
                    @foreach ($models as $sidebar_model)
                        <a
                            class="flex items-start justify-between gap-3 rounded-xl border px-3 py-2 text-sm shadow-sm transition hover:bg-zinc-50 dark:hover:bg-zinc-900/40
                            @if ($sidebar_model['fqcn'] === $model['fqcn']) border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-700/60 dark:bg-emerald-950/40 dark:text-emerald-200
                            @else border-zinc-200 bg-white text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 @endif"
                            href="{{ route('app.model', ['module' => $module['name'], 'model' => $sidebar_model['key']]) }}"
                            @if ($sidebar_model['fqcn'] === $model['fqcn']) aria-current="page" @endif
                        >
                            <span class="font-medium">{{ $sidebar_model['label'] }}</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $sidebar_model['fqcn'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </aside>

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 lg:col-span-2">
            <h1 class="text-lg font-semibold tracking-tight">{{ $model['label'] }}</h1>
            <div class="mt-2 space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                <div><span class="font-medium text-zinc-900 dark:text-zinc-100">FQCN:</span> <code class="rounded bg-zinc-100 px-1 py-0.5 dark:bg-zinc-900">{{ $model['fqcn'] }}</code></div>
                <div><span class="font-medium text-zinc-900 dark:text-zinc-100">Resource:</span> <code class="rounded bg-zinc-100 px-1 py-0.5 dark:bg-zinc-900">{{ $model['resource'] }}</code></div>
            </div>

            <div class="mt-4 rounded-2xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900/30 dark:text-zinc-200">
                <div class="font-semibold text-zinc-900 dark:text-zinc-100">Panoramica</div>
                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    Qui costruiremo le prime funzionalità collegate a questo modello.
                </div>
            </div>
        </section>
    </div>
</x-app.layout>

