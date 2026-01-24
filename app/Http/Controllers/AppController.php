<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AppController extends Controller
{
    public function home(): View
    {
        $modules = $this->activeModules();

        if (! auth('web')->check()) {
            return view('app.login', [
                'modules' => $modules,
            ]);
        }

        return view('app.dashboard', [
            'modules' => $modules,
        ]);
    }

    public function module(string $module): View
    {
        $modules = $this->activeModules();
        $module_info = $this->findActiveModule($modules, $module);
        $models = $this->modelsForModule($module_info['name']);

        return view('app.module', [
            'modules' => $modules,
            'module' => $module_info,
            'models' => $models,
        ]);
    }

    public function model(Request $request, string $module, string $model): View
    {
        $modules = $this->activeModules();
        $module_info = $this->findActiveModule($modules, $module);

        $decoded_model = $this->decodeModelKey($model);

        $models = $this->modelsForModule($module_info['name']);
        $known_model = collect($models)->firstWhere('fqcn', $decoded_model);

        if (! is_array($known_model)) {
            throw new NotFoundHttpException();
        }

        return view('app.model', [
            'modules' => $modules,
            'module' => $module_info,
            'models' => $models,
            'model' => $known_model,
        ]);
    }

    /**
     * @return array<int, array{name: string, description: string, version: string|null, slug: string}>
     */
    private function activeModules(): array
    {
        $statuses_path = base_path('modules_statuses.json');
        $active = [];

        if (File::exists($statuses_path)) {
            /** @var array<string, bool> $decoded */
            $decoded = json_decode((string) File::get($statuses_path), true) ?: [];

            foreach ($decoded as $module => $is_active) {
                if ($is_active !== true) {
                    continue;
                }

                $active[] = (string) $module;
            }
        }

        if ($active === []) {
            $active = collect(File::directories(base_path('Modules')))
                ->map(static fn (string $path): string => basename($path))
                ->values()
                ->all();
        }

        return collect($active)
            ->map(function (string $module): array {
                $composer_path = base_path("Modules/{$module}/composer.json");
                $description = '';
                $version = null;

                if (File::exists($composer_path)) {
                    /** @var array<string, mixed> $composer */
                    $composer = json_decode((string) File::get($composer_path), true) ?: [];
                    $description = (string) ($composer['description'] ?? '');
                    $version = is_string($composer['version'] ?? null) ? (string) $composer['version'] : null;
                }

                return [
                    'name' => $module,
                    'description' => $description,
                    'version' => $version,
                    'slug' => Str::kebab($module),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{name: string, description: string, version: string|null, slug: string}>  $modules
     * @return array{name: string, description: string, version: string|null, slug: string}
     */
    private function findActiveModule(array $modules, string $module): array
    {
        $normalized = Str::lower($module);

        foreach ($modules as $module_info) {
            if ($normalized === Str::lower($module_info['name'])) {
                return $module_info;
            }
        }

        throw new NotFoundHttpException();
    }

    /**
     * @return array<int, array{fqcn: string, label: string, key: string, resource: string}>
     */
    private function modelsForModule(string $module): array
    {
        $resources_path = base_path("Modules/{$module}/app/Filament/Resources");

        if (! File::isDirectory($resources_path)) {
            return [];
        }

        return collect(File::allFiles($resources_path))
            ->filter(static fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), 'Resource.php'))
            ->map(function (SplFileInfo $file): ?array {
                $contents = (string) File::get($file->getPathname());

                $resource_class = $this->parseClassName($contents);
                $model_fqcn = $this->parseModelFqcn($contents);

                if ($resource_class === null || $model_fqcn === null) {
                    return null;
                }

                return [
                    'fqcn' => $model_fqcn,
                    'label' => class_basename($model_fqcn),
                    'key' => $this->encodeModelKey($model_fqcn),
                    'resource' => $resource_class,
                ];
            })
            ->filter()
            ->unique('fqcn')
            ->sortBy('label')
            ->values()
            ->all();
    }

    private function parseClassName(string $php): ?string
    {
        if (! preg_match('/^namespace\s+([^;]+);/m', $php, $ns_matches)) {
            return null;
        }

        if (! preg_match('/^final\s+class\s+([A-Za-z0-9_]+)\s+/m', $php, $class_matches)) {
            return null;
        }

        $namespace = mb_trim((string) ($ns_matches[1] ?? ''));
        $class = mb_trim((string) ($class_matches[1] ?? ''));

        if ($namespace === '' || $class === '') {
            return null;
        }

        return "{$namespace}\\{$class}";
    }

    private function parseModelFqcn(string $php): ?string
    {
        if (! preg_match('/protected\s+static\s+\??string\s+\$model\s*=\s*([^;]+);/m', $php, $matches)) {
            return null;
        }

        $raw = mb_trim((string) ($matches[1] ?? ''));
        $raw = mb_trim($raw, " \t\n\r\0\x0B");

        if (! str_ends_with($raw, '::class')) {
            return null;
        }

        $class_expr = mb_trim(Str::beforeLast($raw, '::class'));
        $class_expr = mb_ltrim($class_expr, '\\');

        if (str_contains($class_expr, '\\')) {
            return $class_expr;
        }

        $short = $class_expr;

        if (! preg_match('/^use\s+([^;]+\\\\' . $short . ');/m', $php, $use_matches)) {
            return null;
        }

        return mb_ltrim((string) ($use_matches[1] ?? ''), '\\');
    }

    private function encodeModelKey(string $fqcn): string
    {
        return mb_rtrim(strtr(base64_encode($fqcn), '+/', '-_'), '=');
    }

    private function decodeModelKey(string $key): string
    {
        $padded = $key . str_repeat('=', (4 - (mb_strlen($key) % 4)) % 4);
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);

        if (! is_string($decoded) || $decoded === '') {
            throw new NotFoundHttpException();
        }

        return $decoded;
    }
}
