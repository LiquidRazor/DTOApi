<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Validation\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class UniqueItemsValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if ($value === null) return;
        if (!is_array($value)) return; // type handled elsewhere

        $seen = [];
        foreach ($value as $k => $v) {
            $key = is_scalar($v) || $v === null ? (string) json_encode($v) : spl_object_hash((object) $v);
            if (isset($seen[$key])) {
                $this->context->buildViolation($constraint->message)
                    ->atPath((string)$k)
                    ->addViolation();
                return;
            }
            $seen[$key] = true;
        }
    }
}
