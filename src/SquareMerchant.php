<?php

namespace Wheniwork\OAuth2\Client\Provider;

class SquareMerchant
{
    public $id;
    public $name;
    public $email;
    public $county_code;
    public $language_code;
    public $currency_code;
    public $business_name;
    public $business_address;
    public $business_phone;
    public $business_type;
    public $shipping_address;
    public $account_type;
    public $account_capabilities;
    public $location_details;
    public $market_url;

    public function __construct(array $attributes)
    {
        $attributes = array_intersect_key($attributes, $this->toArray());
        foreach ($attributes as $key => $value) {
            $this->$key = $value;
        }
    }

    public function toArray()
    {
        return get_object_vars($this);
    }
}
