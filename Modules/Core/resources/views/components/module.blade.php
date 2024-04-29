<div class="p-6 border-gray-200 dark:border-gray-700 md:border-l">
	<div class="flex items-center">
		@include('core::components.barcode-icon')
		<div class="ml-4 text-lg leading-7 font-semibold text-gray-900 dark:text-white">Models</div>
	</div>

	<div class="ml-12">
		@if (empty($data['models']))
		<div class="mt-2 text-sm">
			<span class="text-gray-700">No Model found</span>
		</div>
		@else
		@foreach ($data['models'] as $model)
		<div class="mt-2 text-sm">
			<span class="text-gray-600 dark:text-gray-400">{{ Str::afterLast($model, '\\') }}</span>
		</div>
		@endforeach
		@endif
	</div>
</div>

<div class="p-6 border-gray-200 dark:border-gray-700 md:border-l">
	<div class="flex items-center">
		@include('core::components.route-icon')
		<div class="ml-4 text-lg leading-7 font-semibold text-gray-900 dark:text-white">Controllers</div>
	</div>

	<div class="ml-12">
		@if (empty($data['controllers']))
		<div class="mt-2 text-sm">
			<span class="text-gray-700">No Controller found</span>
		</div>
		@else
		@foreach ($data['controllers'] as $controller)
		<div class="mt-2 text-sm">
			<span class="text-gray-600 dark:text-gray-400">{{ Str::afterLast($controller, '\\') }}</span>
		</div>
		@endforeach
		@endif
	</div>
</div>