<?php
namespace Vanguard;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\GPBUtil;
use Google\Protobuf\RepeatedField;

class Sub2 extends \Google\Protobuf\Internal\Message
{
    protected $a = 0;
    protected $b = 0;
    protected $version = '';

    public function __construct($data = NULL) {
        \GPBMetadata\Authentication::initOnce();
        parent::__construct($data);
    }

    public function getA()
    {
        return $this->a;
    }

    public function setA(int $var)
    {
        GPBUtil::checkInt32($var);
        $this->a = $var;

        return $this;
    }

    public function getB()
    {
        return $this->b;
    }

    public function setB(int $var)
    {
        GPBUtil::checkInt32($var);
        $this->b = $var;

        return $this;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function setVersion(string $var)
    {
        GPBUtil::checkString($var, true);
        $this->version = $var;

        return $this;
    }

}
