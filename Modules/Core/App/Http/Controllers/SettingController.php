<?php

declare(strict_types=1);

namespace Modules\Core\App\Http\Controllers;

use Throwable;
use RuntimeException;
use Doctrine\DBAL\Exception;
use Illuminate\Http\Request;
use InvalidArgumentException;
use UnexpectedValueException;
use Illuminate\Support\Facades\DB;
use Modules\Core\App\Models\Setting;
use Modules\Core\App\Helpers\ResponseBuilder;
use Illuminate\Contracts\Container\BindingResolutionException;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

class SettingController extends Controller
{
    /**
     * Get app translations for the specified locale.
     *
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function getTranslations(Request $request, ?string $lang = null): HttpFoundationResponse
    {
        if ($lang) {
            $lang = mb_substr($lang, 0, 2);
        }
        $languages = translations(true);
        $translations = [];

        foreach ($languages as $language) {
            $short_name = explode(DIRECTORY_SEPARATOR, $language);
            $short_name = array_pop($short_name);

            if ($lang && $short_name !== $lang) {
                continue;
            }

            /** @var string[] */
            $files = glob($language . '/*.php');

            foreach ($files as $file) {
                /** @psalm-suppress UnresolvableInclude */
                $contents = include $file;

                if ($lang) {
                    $translations[basename($file, '.php')] = $contents;
                } else {
                    $translations[$short_name][basename($file, '.php')] = $contents;
                }
            }
        }

        return (new ResponseBuilder($request))
            ->setData($translations)
            ->json();
    }

    /**
     * Get site configs.
     *
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function getSiteConfigs(Request $request): HttpFoundationResponse
    {
        $settings = [];

        foreach (Setting::get() as $s) {
            $settings[$s->name] = $s->value;
        }

        $settings['modules'] = modules();
        $settings['app_name'] = config('app.name');

        return (new ResponseBuilder($request))
            ->setData($settings)
            ->json();
    }

    /**
     * Get site info.
     *
     *
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     * @throws Exception
     * @throws RuntimeException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function siteInfo(Request $request): HttpFoundationResponse
    {
        $data = [
            'version' => version(),
            'db' => $this->db(),
        ];

        return (new ResponseBuilder($request))
            ->setData($data)
            ->json();
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws RuntimeException
     *
     * @psalm-return array{driver: mixed, dbname: mixed}
     */
    private function db(): array
    {
        $connection = DB::connection();

        $config = $connection->getConfig();

        return [
            'driver' => $config['driver'],
            'dbname' => $config['database'],
        ];
    }
}
