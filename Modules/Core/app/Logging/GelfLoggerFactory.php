<?php

declare(strict_types=1);

namespace Modules\Core\Logging;

use Gelf\Publisher;
use Monolog\Logger;
use Monolog\Handler\GelfHandler;
use Monolog\Formatter\GelfMessageFormatter;
use Gelf\Transport\IgnoreErrorTransportWrapper;
use Hedii\LaravelGelfLogger\GelfLoggerFactory as BaseGelfLoggerGelfLoggerFactory;

class GelfLoggerFactory extends BaseGelfLoggerGelfLoggerFactory
{
	#[\Override]
	public function __invoke(array $config): Logger
	{
		$config = $this->parseConfig($config);

		$transport = $this->getTransport(
			$config['transport'],
			$config['host'],
			$config['port'],
			$config['chunk_size'],
			$config['path'],
			$this->enableSsl($config) ? $this->sslOptions($config['ssl_options']) : null
		);

		if ($config['ignore_error']) {
			$transport = new IgnoreErrorTransportWrapper($transport);
		}

		$handler = new GelfHandler(new Publisher($transport), $this->level($config));

		$handler->setFormatter(
			new GelfMessageFormatter(
				$config['system_name'],
				$config['extra_prefix'],
				$config['context_prefix'],
				$config['max_length']
			)
		);

		foreach ($this->parseProcessors($config) as $processor) {
			if (is_array($processor)) {
				if (!array_key_exists('processor', $processor)) throw new \Exception("Not a valid processor configuration. array key 'processor' expected");
				$class = $processor['processor'];
				$args = $processor['with'] ?? [];
				$instance = new $class(...$args);
			} else {
				$instance = new $processor;
			}
			$handler->pushProcessor($instance);
		}

		return new Logger($this->parseChannel($config), [$handler]);
	}
}
