<?php
namespace Vanguard;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\GPBUtil;
use Google\Protobuf\RepeatedField;

class vg_version extends \Google\Protobuf\Internal\Message
{
    protected $a = 0;
    protected $b = 0;
    protected $c = 0;
    protected $d = 0;

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

    public function getC()
    {
        return $this->c;
    }

    public function setC(int $var)
    {
        GPBUtil::checkInt32($var);
        $this->c = $var;

        return $this;
    }

    public function getD()
    {
        return $this->d;
    }

    public function setD(int $var)
    {
        GPBUtil::checkInt32($var);
        $this->d = $var;

        return $this;
    }

}
