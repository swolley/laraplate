<?php

return [
	'facebook' => [
		'client_id' => env('FACEBOOK_CLIENT_ID'),
		'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
		'redirect' => env('SOCIALITE_REDIRECT'),
	],
	'x' => [
		'client_id' => env('X_CLIENT_ID'),
		'client_secret' => env('X_CLIENT_SECRET'),
		'redirect' => env('SOCIALITE_REDIRECT'),
	],
	'linkedin-openid' => [
		'client_id' => env('LINKEDIN_OPENID_CLIENT_ID'),
		'client_secret' => env('LINKEDIN_OPENID_CLIENT_SECRET'),
		'redirect' => env('SOCIALITE_REDIRECT'),
	],
	'google' => [
		'client_id' => env('GOOGLE_CLIENT_ID'),
		'client_secret' => env('GOOGLE_CLIENT_SECRET'),
		'redirect' => env('SOCIALITE_REDIRECT'),
	],
	'github' => [
		'client_id' => env('GITHUB_CLIENT_ID'),
		'client_secret' => env('GITHUB_CLIENT_SECRET'),
		'redirect' => env('SOCIALITE_REDIRECT'),
	],
	'gitlab' => [
		'client_id' => env('GITLAB_CLIENT_ID'),
		'client_secret' => env('GITLAB_CLIENT_SECRET'),
		'redirect' => env('SOCIALITE_REDIRECT'),
	],
	'bitbucket' => [
		'client_id' => env('BITBUCKET_CLIENT_ID'),
		'client_secret' => env('BITBUCKET_CLIENT_SECRET'),
		'redirect' => env('SOCIALITE_REDIRECT'),
	],
	'slack' => [
		'client_id' => env('SLACK_CLIENT_ID'),
		'client_secret' => env('SLACK_CLIENT_SECRET'),
		'redirect' => env('SOCIALITE_REDIRECT'),
	],
	'slack-openid' => [
		'client_id' => env('SLACK_OPENID_CLIENT_ID'),
		'client_secret' => env('SLACK_OPENID_CLIENT_SECRET'),
		'redirect' => env('SOCIALITE_REDIRECT'),
	],
];
