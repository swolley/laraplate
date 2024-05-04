<?php

namespace Modules\Core\Logging;

use Monolog\LogRecord;
use Illuminate\Support\Facades\Auth;
use Monolog\Processor\ProcessorInterface;
// use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\PsrLogMessageProcessor;

class GelfAdditionalInfoProcessor implements ProcessorInterface
{
	// private IntrospectionProcessor $introspectionProcessor;
	private PsrLogMessageProcessor $psrLogMessageProcessor;

	public function __construct()
	{
		// 	$this->introspectionProcessor = new IntrospectionProcessor(Level::Error, [], 4);
		$this->psrLogMessageProcessor = new PsrLogMessageProcessor(removeUsedContextFields: true);
	}

	public function __invoke(LogRecord $record): LogRecord
	{
		// $this->introspectionProcessor->__invoke($record);
		$record = $this->psrLogMessageProcessor->__invoke($record);

		$extra = ['user' => Auth::user()->username ?? 'anonymous', 'application_name' => config('app.name')];
		$record->extra += $extra;

		return $record;
	}
}
