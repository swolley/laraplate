<?php

return [
	'verify_new_user' => env('VERIFY_NEW_USER', false),
	'enable_user_registration' => env('ENABLE_USER_REGISTRATION', false),
	'enable_user_login' => env('ENABLE_SOCIAL_LOGIN', false),

	'providers' => [
		'users' => [
			'driver' => 'eloquent',
			'model' => env('AUTH_MODEL', Modules\Core\Models\User::class),
		],
	],

];
