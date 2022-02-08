<?php

namespace Grnspc\Addresses;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Grnspc\Addresses\Exceptions\FailedValidationException;

trait HasAddresses
{
	protected array $queuedAddress = [];

	public static function bootHasAddresses()
	{
        static::created(function (Model $taggableModel) {
            if (count($taggableModel->queuedAddress) === 0) {
                return;
            }

            $taggableModel->attachTags($taggableModel->queuedAddress);

            $taggableModel->queuedAddress = [];
        });

		static::deleted(function (Model $deletedModel) {
			$deletedModel->flushAddresses();
		});
	}

	public static function getAddressClassName(): string
	{
		return config('address.model', Address::class);
	}

	public function addresses(): MorphMany
	{
		return $this->morphMany(self::getAddressClassName(), 'addressable');
	}

	public function storeAddress(array $attributes)
	{
		if (!$this->exists) {
            $this->queuedAddresses = $attributes;

		    return;
		}

		$attributes = $this->loadAddressAttributes($attributes);

		return $this->addresses()->updateOrCreate($attributes);
	}

	public function updateAddress(int | Address $address, array $attributes): bool
	{
		$address = $this->getStoredAddress($address);
		if (!$address instanceof Address) {
			return false;
		}

		$attributes = $this->loadAddressAttributes($attributes);

		return $address->update($attributes);
	}

	public function destroyAddress(Address $address): bool
	{
		return $this->addresses()
			->where('id', $address->id)
			->delete();
	}

	public function flushAddresses(): bool
	{
		return $this->addresses()->delete();
	}

	public function getAddress(string $flag = 'is_primary', string $direction = 'desc'): ?Address
	{
		$flag = Str::startsWith($flag, 'is_') ? $flag : "is_{$flag}";

		$addresses = $this->addresses;

		if (
			$addresses->count() === 1 ||
			!array_key_exists($flag, config('address.flags', [])) ||
			$addresses->where($flag, true)->count() === 0
		) {
			return $addresses->first();
		}

		return $addresses
			// ->Flag($flag)
			->orderBy($flag, $direction)
			->first();
	}

	public function hasAddresses(): bool
	{
		return (bool) count($this->addresses);
	}

	protected function getStoredAddress($address): Address
	{
		$addressClass = self::getAddressClassName();

		if (is_numeric($address)) {
			return $addressClass::find($address);
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
		$rules = self::getAddressClassName()::getValidationRules();

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
