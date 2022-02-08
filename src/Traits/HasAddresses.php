<?php

namespace Grnspc\Addresses\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Grnspc\Addresses\AddressRegistrar;
use Grnspc\Addresses\Contracts\Address;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Grnspc\Addresses\Exceptions\FailedValidationException;

trait HasAddresses
{
	/** @var string */
	private $addressClass;

	/**
	 * Boot the addressable trait for the model.
	 *
	 * @return void
	 */
	public static function bootAddressable()
	{
		static::deleted(function (self $model) {
			$model->addresses()->delete();
		});
	}

	public function getAddressClass(): Address
	{
		if (!isset($this->addressClass)) {
			$this->addressClass = app(AddressRegistrar::class)->getAddressClass();
		}

		return $this->addressClass;
	}

	/**
	 * Get all attached addresses to the model.
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\MorphMany
	 */
	public function addresses(): MorphMany
	{
		return $this->morphMany(config('grnspc.addresses.model'), 'addressable', 'addressable_type', 'addressable_id');
	}

	/**
	 * Add an address to this model.
	 *
	 * @param  array  $attributes
	 * @return mixed
	 * @throws Exception
	 */
	public function storeAddress(array $attributes)
	{
		// $model = $this->getModel();

		// if (!$model->exists) {
		// 	return;
		// }

		$attributes = $this->loadAddressAttributes($attributes);

		return $this->addresses()->updateOrCreate($attributes);
	}

	/**
	 * Updates the given address.
	 *
	 * @param  Address|int  $address
	 * @param  array    $attributes
	 * @return bool
	 * @throws Exception
	 */
	public function updateAddress(Address|int $address, array $attributes): bool
	{
		$address = $this->getStoredAddress($address);
		if (!$address instanceof Address) {
			return false;
		}

		$attributes = $this->loadAddressAttributes($attributes);

		return $address->update($attributes);
	}

	/**
	 * Deletes given address.
	 *
	 * @param  Address  $address
	 * @return bool
	 * @throws Exception
	 */
	public function destroyAddress(Address $address): bool
	{
		return $this->addresses()
			->where('id', $address->id)
			->delete();
	}

	/**
	 * Deletes all the addresses of this model.
	 *
	 * @return bool
	 */
	public function flushAddresses(): bool
	{
		return $this->addresses()->delete();
	}

	/**
	 * Get the primary address.
	 *
	 * @param  string  $direction
	 * @return Address|null
	 */
	public function getAddress(string $flag = 'is_primary', string $direction = 'desc'): ?Address
	{
		$flag = Str::startsWith($flag, 'is_') ? $flag : "is_{$flag}";

		$addresses = $this->addresses;

		if (
			$this->addresses->count() === 1 ||
			!array_key_exists($flag, config('address.flags', [])) ||
			$this->addresses->where($flag, true)->count() === 0
		) {
			return $this->addresses->first();
		}

		return $this->addresses
			// ->Flag($flag)
			->orderBy($flag, $direction)
			->first();
	}

	/**
	 * Check if model has addresses.
	 *
	 * @return bool
	 */
	public function hasAddresses(): bool
	{
		return (bool) count($this->addresses);
	}

	protected function getStoredAddress($address): Address
	{
		$addressClass = $this->getAddressClass();

		if (is_numeric($address)) {
			return $addressClass->find($address);
		}

		return $address;
	}

	/**
	 * Add country id to attributes array.
	 *
	 * @param  array  $attributes
	 * @return array
	 * @throws FailedValidationException
	 */
	public function loadAddressAttributes(array $attributes): array
	{
		// run validation
		$validator = $this->validateAddress($attributes);

		if ($validator->fails()) {
			$errors = $validator->errors()->all();
			$error = '[Addresses] ' . implode(' ', $errors);

			throw new FailedValidationException($error);
		}

		// return attributes array with country_id key/value pair
		return $attributes;
	}

	/**
	 * Validate the address.
	 *
	 * @param  array  $attributes
	 * @return Validator
	 */
	function validateAddress(array $attributes): Validator
	{
		$rules = $this->getAddressClass()->getValidationRules();

		return validator($attributes, $rules);
	}

	/**
	 * Find addressables by distance.
	 *
	 * @param string $distance
	 * @param string $unit
	 * @param string $latitude
	 * @param string $longitude
	 *
	 * @return \Illuminate\Support\Collection
	 */
	public static function findByDistance($distance, $unit, $latitude, $longitude): Collection
	{
		$addressModel = (new self())->getAddressClass();
		$records = (new $addressModel())->within($distance, $unit, $latitude, $longitude)->get();

		$results = [];
		foreach ($records as $record) {
			$results[] = $record->addressable;
		}

		return new Collection($results);
	}
}
