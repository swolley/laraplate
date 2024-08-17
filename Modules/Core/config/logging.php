<?php

declare(strict_types=1);

use Modules\Core\Logging\GelfLoggerFactory;
use Monolog\Formatter\GelfMessageFormatter;
use Modules\Core\Logging\GelfAdditionalInfoProcessor;

return [
	'channels' => [
		'graylog' => [
			'driver' => 'custom',
			'level' => env('GRAYLOG_LEVEL', 'error'),
			'via' => GelfLoggerFactory::class,
			'host' => env('GRAYLOG_URL'),
			'port' => env('GRAYLOG_PORT', 12201),
			'formatter' => GelfMessageFormatter::class,
			'processors' => [
				[
					'processor' => GelfAdditionalInfoProcessor::class,
					// 'with' => ['channel' => $channel],
				],
			],
		],
	],
];
