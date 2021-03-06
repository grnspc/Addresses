<?php

return [
	'table' => 'addresses',

	'model' => Grnspc\Addresses\Address::class,

	'flags' => ['primary', 'billing', 'shipping'],

	'rules' => [
		'label' => ['nullable', 'string', 'max:150'],
		'given_name' => ['nullable', 'string', 'max:150'],
		'family_name' => ['nullable', 'string', 'max:150'],
		'organization' => ['nullable', 'string', 'max:150'],
		'street' => ['required', 'string', 'max:255'],
		'street_extra' => ['nullable', 'string', 'max:255'],
		'city' => ['required', 'string', 'max:150'],
		'province' => ['required', 'string', 'max:150'],
		'post_code' => ['nullable', 'string', 'max:150'],
		'country_code' => ['required', 'alpha', 'size:2', 'country'],
		'latitude' => ['nullable', 'numeric'],
		'longitude' => ['nullable', 'numeric'],
	],

	'geocoding' => [
		'enabled' => false,
		'api_key' => env('GOOGLE_APP_KEY'),
	],
];
