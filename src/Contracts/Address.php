<?php
namespace Grnspc\Addresses\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Interface
 * @package Grnspc\Addresses\Contracts
 */
interface Address
{

	/**
	 * Get the owner model of the address.
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\MorphTo
	 */
	public function addressable(): MorphTo;
}
