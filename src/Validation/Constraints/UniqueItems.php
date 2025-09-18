<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Validation\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute]
final class UniqueItems extends Constraint
{
    public string $message = 'This collection contains duplicate values.';
    public function validatedBy(): string {
        return UniqueItemsValidator::class;
    }
}
