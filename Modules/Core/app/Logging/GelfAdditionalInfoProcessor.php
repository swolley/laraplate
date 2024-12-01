<?php

declare(strict_types=1);

namespace Modules\Core\Logging;

use Monolog\LogRecord;
use Illuminate\Support\Facades\Auth;
use Monolog\Processor\ProcessorInterface;
use Monolog\Processor\PsrLogMessageProcessor;

class GelfAdditionalInfoProcessor implements ProcessorInterface
{
	private PsrLogMessageProcessor $psrLogMessageProcessor;
	private $channel;

	public function __construct(?string $channel = null)
	{
		$this->channel = $channel;
		$this->psrLogMessageProcessor = new PsrLogMessageProcessor(removeUsedContextFields: true);
	}

	public function __invoke(LogRecord $record): LogRecord
	{
		$record = $this->psrLogMessageProcessor->__invoke($record);

		$extra = [
			'user' => Auth::user()->username ?? 'anonymous',
			'application_name' => config('app.name'),
			'channel' => $this->channel ?? config('logging.default'),
		];
		$record->extra = array_merge($record->extra, $extra);

		return $record;
	}
}
