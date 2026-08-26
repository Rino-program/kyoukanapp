<?php
return [
	'db' => [
		'dsn' => 'mysql:host=localhost;dbname=YOUR_DATABASE;charset=utf8mb4',
		'user' => 'YOUR_DATABASE_USER',
		'password' => 'YOUR_DATABASE_PASSWORD',
	],
	'jwt_secret' => 'replace-with-a-long-random-secret',
	'google_client_id' => 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com',
	'allowed_origins' => ['https://YOUR_GITHUB_USERNAME.github.io'],
	'mail' => ['from' => 'classspace@example.com', 'enabled' => false],
];
