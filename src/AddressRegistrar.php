<?php

namespace Grnspc\Addresses;

use Grnspc\Addresses\Contracts\Address;

class AddressRegistrar
{
    /** @var string */
    protected $addressClass;

     /**
     * AddressRegistrar constructor.
     */
    public function __construct()
    {
        $this->addressClass = config('address.models.address');
    }

      /**
     * Get an instance of the addredd class.
     *
     * @return \Grnspc\Addresses\Contracts\Address
     */
    public function getAddressClass(): Address
    {
        return app($this->addressClass);
    }

     public function setAddressClass($addressClass)
    {
        $this->addressClass = $addressClass;
        config()->set('address.models.address', $addressClass);
        app()->bind(Address::class, $addressClass);

        return $this;
    }

}
