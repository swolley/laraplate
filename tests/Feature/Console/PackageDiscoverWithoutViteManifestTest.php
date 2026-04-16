<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('completes package discover in a subprocess when the vite manifest is absent', function (): void {
    $manifest_path = public_path('build/manifest.json');
    $had_manifest = is_file($manifest_path);
    $manifest_backup = $had_manifest ? (string) file_get_contents($manifest_path) : null;

    if ($had_manifest) {
        unlink($manifest_path);
    }

    try {
        $process = new Process([PHP_BINARY, base_path('artisan'), 'package:discover', '--no-interaction'], base_path());
        $process->run();

        expect($process->isSuccessful())->toBeTrue();
    } finally {
        if ($manifest_backup !== null) {
            $directory = dirname($manifest_path);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($manifest_path, $manifest_backup);
        }
    }
});
