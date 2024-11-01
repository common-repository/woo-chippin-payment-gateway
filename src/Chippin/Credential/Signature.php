<?php

namespace Chippin\Credential;

use Chippin\Credential;
use Chippin\Exception;

class Signature extends Credential
{

    private $merchant_id;
    private $merchant_secret;

    /**
     * Interface for chippin creation specific fields
     * @var array
     */
    protected $_orderFields = array(
        'merchant_id', 'merchant_order_id', 'total_amount', 'first_name', 'last_name', 'email', 'duration', 'grace_period', 'currency_code', 'hmac'
    );

    /**
     * Interface for chippin hash specific fields
     * @var array
     */
    protected $_orderHashFields = array(
        'merchant_order_id', 'total_amount', 'duration', 'grace_period', 'currency_code'
    );

    /**
     * Interface for chippin product specific fields
     * @var array
     */
    protected $_chippinProductFields = array(
        'label', 'image', 'amount'
    );

    /**
     * Creates a new instance of signature to sign all data
     *
     */
    public function __construct($merchant_id, $merchant_secret)
    {
        $this->merchant_id  = $merchant_id;
        $this->merchant_secret  = $merchant_secret;
    }

    public function getMerchantId() {
        return $this->merchant_id;
    }

    public function getMerchantSecret() {
        return $this->merchant_secret;
    }

    public function generateContributionHash($merchant_order_id, $first_name, $last_name, $email)
    {
        return $this->generateHash(sprintf(
            'contributed%s%s%s%s%s',
            $this->getMerchantId(),
            $merchant_order_id,
            $first_name,
            $last_name,
            $email
        ));
    }

    public function generateCallbackHash($callbackKey = 'invited', $merchant_order_id)
    {
        return $this->generateHash(sprintf('%s%s%s', $callbackKey, $this->merchant_id, $merchant_order_id));
    }

    /**
     * Generate a hash from the required query params to authenticate the order
     *
     * @return String
     *
     */
    public function generateOrderHash($data)
    {
        $hashParts =  array();
        $hashParts[] = $this->getMerchantId();
        foreach ($this->_orderHashFields as $field) {
            if (empty($data[$field])) {
                throw new Exception(sprintf('The value for %s must be passed to generate the hmac hash', $field));
            }
            $hashParts[] = $data[$field];
        }

        return $this->generateHash(join($hashParts));
    }

    private function generateHash($string)
    {
        return hash_hmac('sha256', $string, $this->getMerchantSecret());
    }
}
