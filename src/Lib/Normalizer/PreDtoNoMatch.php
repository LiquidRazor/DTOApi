<?php

namespace LiquidRazor\DtoApiBundle\Lib\Normalizer;

class PreDtoNoMatch
{

    private static ?self $i = null;
    public static function instance(): self { return self::$i ??= new self(); }
    private function __construct() {}
}