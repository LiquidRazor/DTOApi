<?php

namespace LiquidRazor\DtoApiBundle\Includes\Contracts;

interface ResponseDTO
{
    /**
     * $pre is a std-like map (stdClass for objects, arrays for lists).
     */
    public static function fromControllerResponse(mixed $pre): static;
}