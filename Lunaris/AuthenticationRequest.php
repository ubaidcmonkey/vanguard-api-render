<?php
namespace Vanguard;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\GPBUtil;
use Google\Protobuf\RepeatedField;

class AuthenticationRequest extends \Google\Protobuf\Internal\Message
{
    protected $machine_id = '';
    protected $field2 = null;
    protected $game_token = '';
    protected $client_rsa_public_key = '';
    protected $version1 = null;
    protected $version2 = null;
    protected $game_id = '';
    protected $boot_state = 0;
    private $ephemeral_identifiers;
    protected $external_sid = '';

    public function __construct($data = NULL) {
        \GPBMetadata\Authentication::initOnce();
        parent::__construct($data);
    }

    public function getMachineId()
    {
        return $this->machine_id;
    }

    public function setMachineId(string $var)
    {
        GPBUtil::checkString($var, true);
        $this->machine_id = $var;

        return $this;
    }

    public function getField2()
    {
        return $this->field2;
    }

    public function hasField2()
    {
        return isset($this->field2);
    }

    public function clearField2()
    {
        unset($this->field2);
    }

    public function setField2(\Vanguard\Sub2|null $var)
    {
        $this->field2 = $var;

        return $this;
    }

    public function getGameToken()
    {
        return $this->game_token;
    }

    public function setGameToken(string $var)
    {
        GPBUtil::checkString($var, true);
        $this->game_token = $var;

        return $this;
    }

    public function getClientRsaPublicKey()
    {
        return $this->client_rsa_public_key;
    }

    public function setClientRsaPublicKey(string $var)
    {
        GPBUtil::checkString($var, true);
        $this->client_rsa_public_key = $var;

        return $this;
    }

    public function getVersion1()
    {
        return $this->version1;
    }

    public function hasVersion1()
    {
        return isset($this->version1);
    }

    public function clearVersion1()
    {
        unset($this->version1);
    }

    public function setVersion1(\Vanguard\vg_version|null $var)
    {
        $this->version1 = $var;

        return $this;
    }

    public function getVersion2()
    {
        return $this->version2;
    }

    public function hasVersion2()
    {
        return isset($this->version2);
    }

    public function clearVersion2()
    {
        unset($this->version2);
    }

    public function setVersion2(\Vanguard\vg_version|null $var)
    {
        $this->version2 = $var;

        return $this;
    }

    public function getGameId()
    {
        return $this->game_id;
    }

    public function setGameId(string $var)
    {
        GPBUtil::checkString($var, true);
        $this->game_id = $var;

        return $this;
    }

    public function getBootState()
    {
        return $this->boot_state;
    }

    public function setBootState(int $var)
    {
        GPBUtil::checkInt32($var);
        $this->boot_state = $var;

        return $this;
    }

    public function getEphemeralIdentifiers()
    {
        return $this->ephemeral_identifiers;
    }

    public function setEphemeralIdentifiers(array|RepeatedField $var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::STRING);
        $this->ephemeral_identifiers = $arr;

        return $this;
    }

    public function getExternalSid()
    {
        return $this->external_sid;
    }

    public function setExternalSid(string $var)
    {
        GPBUtil::checkString($var, true);
        $this->external_sid = $var;

        return $this;
    }

}
