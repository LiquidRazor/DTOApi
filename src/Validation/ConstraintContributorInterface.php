<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Validation;

use LiquidRazor\DtoApiBundle\Lib\Attributes\DtoApiProperty;
use ReflectionProperty;

interface ConstraintContributorInterface
{
    /**
     * Return an iterable of Constraints to add for this property.
     * Implementations may inspect $meta->x or any naming conventions.
     *
     * @return iterable<object> Symfony constraints
     */
    public function contribute(ReflectionProperty $property, DtoApiProperty $meta): iterable;
}
