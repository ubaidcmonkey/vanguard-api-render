<?php
namespace Vanguard;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\GPBUtil;
use Google\Protobuf\RepeatedField;

class AuthenticationResponse extends \Google\Protobuf\Internal\Message
{
    protected $token = '';
    protected $expiry = '';
    protected $unknown_id = 0;
    protected $server_rsa_public_key = '';
    private $signatures;
    protected $session_id = '';
    protected $unknown_value = 0;

    public function __construct($data = NULL) {
        \GPBMetadata\Tokenresp::initOnce();
        parent::__construct($data);
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken(string $var)
    {
        GPBUtil::checkString($var, true);
        $this->token = $var;

        return $this;
    }

    public function getExpiry()
    {
        return $this->expiry;
    }

    public function setExpiry(string $var)
    {
        GPBUtil::checkString($var, true);
        $this->expiry = $var;

        return $this;
    }

    public function getUnknownId()
    {
        return $this->unknown_id;
    }

    public function setUnknownId(int $var)
    {
        GPBUtil::checkUint32($var);
        $this->unknown_id = $var;

        return $this;
    }

    public function getServerRsaPublicKey()
    {
        return $this->server_rsa_public_key;
    }

    public function setServerRsaPublicKey(string $var)
    {
        GPBUtil::checkString($var, true);
        $this->server_rsa_public_key = $var;

        return $this;
    }

    public function getSignatures()
    {
        return $this->signatures;
    }

    public function setSignatures(array|RepeatedField $var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::BYTES);
        $this->signatures = $arr;

        return $this;
    }

    public function getSessionId()
    {
        return $this->session_id;
    }

    public function setSessionId(string $var)
    {
        GPBUtil::checkString($var, true);
        $this->session_id = $var;

        return $this;
    }

    public function getUnknownValue()
    {
        return $this->unknown_value;
    }

    public function setUnknownValue(int $var)
    {
        GPBUtil::checkUint32($var);
        $this->unknown_value = $var;

        return $this;
    }

}
