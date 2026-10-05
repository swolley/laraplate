<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Refuses to start the test suite when it could reach a real database.
 */
final class TestEnvironmentGuard
{
    private const array LOCAL_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    private const array TRUTHY = ['1', 'true', 'yes', 'on'];

    /**
     * @param  array<string, mixed>  $env
     * @return list<string>
     */
    public static function violations(string $basePath, array $env): array
    {
        $violations = [];

        if (file_exists($basePath . '/bootstrap/cache/config.php')) {
            $violations[] = 'bootstrap/cache/config.php exists: a cached config makes the tests ignore phpunit.xml and use the database from .env. Run `php artisan config:clear`.';
        }

        $connection = self::value($env, 'DB_CONNECTION');
        $host = self::value($env, 'DB_HOST');
        $allowed = in_array(mb_strtolower(self::value($env, 'ALLOW_REMOTE_TEST_DATABASE')), self::TRUTHY, true);

        if ($connection !== 'sqlite' && ! in_array($host, self::LOCAL_HOSTS, true) && ! $allowed) {
            $violations[] = sprintf(
                'The test database is not safe (DB_CONNECTION="%s", DB_HOST="%s"): only sqlite or a local host (127.0.0.1, localhost, ::1) is accepted. Fix phpunit.xml/.env, or set ALLOW_REMOTE_TEST_DATABASE=1 to opt out knowingly.',
                $connection,
                $host,
            );
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $env
     */
    public static function assertSafe(string $basePath, array $env): void
    {
        $violations = self::violations($basePath, $env);

        if ($violations !== []) {
            throw new RuntimeException(
                "Unsafe test environment, refusing to run the tests:\n- " . implode("\n- ", $violations),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $env
     */
    private static function value(array $env, string $key): string
    {
        $value = $env[$key] ?? '';

        return is_scalar($value) ? mb_trim((string) $value) : '';
    }
}
