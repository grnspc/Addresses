<?php

namespace Grnspc\Addresses\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Grnspc\Addresses\Traits\GeoDistanceTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Grnspc\Addresses\Contracts\Address as AddressContract;

class Address extends Model implements AddressContract
{
	use GeoDistanceTrait;
	use SoftDeletes;

	public const FLAGS = ['primary', 'billing', 'shipping'];

	protected $guarded = ['uuid'];

	/** @inheritdoc */
	protected $casts = [
		'addressable_id' => 'integer',
		'addressable_type' => 'string',
		'label' => 'string',
		'given_name' => 'string',
		'family_name' => 'string',
		'organization' => 'string',
		'street' => 'string',
		'street_extra' => 'string',
		'province' => 'string',
		'city' => 'string',
		'post_code' => 'string',
		'country_code' => 'string',
		'extra' => 'array',
		'latitude' => 'float',
		'longitude' => 'float',
		'deleted_at' => 'datetime',
	];

	/**
	 * Create a new Eloquent model instance.
	 *
	 * @param array $attributes
	 */
	public function __construct(array $attributes = [])
	{
		parent::__construct($attributes);

		$this->guarded[] = $this->primaryKey;
	}

	public function getTable()
	{
		return config('address.tables.addresses', parent::getTable());
	}

	/** @inheritdoc */
	protected static function booted()
	{
		static::creating(function ($model) {
			if (
				$model
					->getConnection()
					->getSchemaBuilder()
					->hasColumn($model->getTable(), 'uuid')
			) {
				$model->uuid = Str::uuid();
			}
		});

		static::saving(function (self $address) {
			if (config('address.geocoding.enabled')) {
				$address->geocode();
			}
		});
	}

	/**
	 * Get the validation rules.
	 *
	 * @return array
	 */
	public static function getValidationRules(): array
	{
		$rules = config('address.rules', [
			'label' => ['nullable', 'string', 'max:150'],
			'given_name' => ['nullable', 'string', 'max:150'],
			'family_name' => ['nullable', 'string', 'max:150'],
			'organization' => ['nullable', 'string', 'max:150'],
			'street' => ['required', 'string', 'max:255'],
			'street_extra' => ['nullable', 'string', 'max:255'],
			'city' => ['required', 'string', 'max:150'],
			'province' => ['required', 'string', 'max:150'],
			'post_code' => ['required', 'string', 'max:150'],
			'country_code' => ['required', 'alpha', 'size:2', 'country'],
			'latitude' => ['nullable', 'numeric'],
			'longitude' => ['nullable', 'numeric'],
		]);

		foreach (config('address.flags', self::FLAGS) as $flag) {
			$rules['is_' . $flag] = ['boolean'];
		}

		return $rules;
	}

	/**
	 * Get the owner model of the address.
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\MorphTo
	 */
	public function addressable(): MorphTo
	{
		return $this->morphTo('addressable', 'addressable_type', 'addressable_id', 'id');
	}

	/**
	 * Scope primary addresses
	 *
	 * @param  Builder  $query
	 * @return Builder
	 */
	public function scopeFlag(Builder $query, string $flag = 'is_primary'): Builder
	{
		$flag = Str::startsWith($flag, 'is_') ? $flag : "is_{$flag}";
		return $query->where($flag, true);
	}

	/**
	 * Scope addresses by the given country.
	 *
	 * @param \Illuminate\Database\Eloquent\Builder $builder
	 * @param string                                $countryCode
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public function scopeInCountry(Builder $builder, string $countryCode): Builder
	{
		return $builder->where('country_code', $countryCode);
	}

	/**
	 * Get full name attribute.
	 *
	 * @return string
	 */
	public function getFullNameAttribute(): string
	{
		return implode(' ', [$this->given_name, $this->family_name]);
	}

	public function geocode(): self
	{
		$geocoding_api_key = config('grnspc.addresses.geocoding.api_key');

		if (!($query = $this->getQueryString()) && !$geocoding_api_key) {
			return $this;
		}

		$url = "https://maps.google.com/maps/api/geocode/json?address={$query}&sensor=false&key={$geocoding_api_key}";

		if ($geocode = file_get_contents($url)) {
			$output = json_decode($geocode);

			if (count($output->results) && isset($output->results[0])) {
				if ($geo = $output->results[0]->geometry) {
					$this->latitude = $geo->location->lat;
					$this->longitude = $geo->location->lng;
				}
			}
		}

		return $this;
	}

	/**
	 * Get the encoded query string.
	 *
	 * @return string
	 */
	public function getQueryString(): string
	{
		$query = [];

		$query[] = $this->street ?: '';
		//  $query[] = $this->line_2                        ?: '';
		$query[] = $this->city ?: '';
		$query[] = $this->province ?: '';
		$query[] = $this->postal_code ?: '';
		$query[] = country($this->country_code)->getName() ?: '';

		$query = trim(implode(',', array_filter($query)));

		return urlencode($query);
	}

	/**
	 * Get the address as array.
	 *
	 * @return array
	 */
	public function getArray(): array
	{
		$address = $one = $two = $three = [];

		$one[] = $this->street ?: '';
		$one[] = $this->street_extra ?: '';

		$two[] = $this->city ? "{$this->city}," : '';
		$two[] = $this->province ?: '';

		$three[] = $this->post_code ?: '';

		$address[] = implode(', ', array_filter($one));
		$address[] = implode(', ', array_filter($two));
		$address[] = $this->country_name ?: '';

		if (count($address = array_filter($address)) > 0) {
			return $address;
		}

		return [];
	}

	/**
	 * Get the address as html block.
	 *
	 * @return string
	 */
	public function toHtml(bool $withCountry = true): string
	{
		if ($address = $this->getArray()) {
			if (!$withCountry && count($address) == 3) {
				array_pop($address);
			}

			return '<address>' . implode('<br />', array_filter($address)) . '</address>';
		}

		return '';
	}

	/**
	 * Get the address as a simple line.
	 *
	 * @param  string  $glue
	 * @return string
	 */
	public function getLine($glue = ', '): string
	{
		if ($address = $this->getArray()) {
			return implode($glue, array_filter($address));
		}

		return '';
	}

	/**
	 * Get the country name.
	 *
	 * @return string
	 */
	public function getCountryNameAttribute(): string
	{
		if ($this->country_code) {
			return country($this->country_code)->getName();
		}

		return '';
	}
}
