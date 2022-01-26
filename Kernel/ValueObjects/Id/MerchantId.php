<?php

namespace PlugHacker\PlugCore\Kernel\ValueObjects\Id;

use PlugHacker\PlugCore\Kernel\ValueObjects\AbstractValidString;

class MerchantId extends AbstractValidString
{
    protected function validateValue($value)
    {
        return preg_match('/^merch_\w{16}$/', $value) === 1;
    }
}
