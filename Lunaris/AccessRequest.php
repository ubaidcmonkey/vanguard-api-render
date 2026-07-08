<?php
namespace Vanguard;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\GPBUtil;
use Google\Protobuf\RepeatedField;

class AccessRequest extends \Google\Protobuf\Internal\Message
{
    protected $token = '';

    public function __construct($data = NULL) {
        \GPBMetadata\Accessrequest::initOnce();
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

}
