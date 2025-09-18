<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Validation;

use LiquidRazor\DtoApiBundle\Lib\Attributes\DtoApiProperty;
use ReflectionException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use ReflectionClass;

final readonly class DtoApiConstraintLoader implements LoaderInterface
{
    public function __construct(
        private PropertyConstraintMapper $mapper,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        $reflectedClass = new ReflectionClass($metadata->getClassName());
        $loadedAny = false;

        foreach ($reflectedClass->getProperties() as $attributeProperty) {
            $propertyAttributes = $attributeProperty->getAttributes(DtoApiProperty::class);
            if ($propertyAttributes === []) {
                continue;
            }
            /** @var DtoApiProperty $meta */
            $meta = $propertyAttributes[0]->newInstance();
            /** @var Constraint[] $constraints */
            $constraints = $this->mapper->map($attributeProperty, $meta);
            if ($constraints !== []) {
                $metadata->addPropertyConstraints($attributeProperty->getName(), $constraints);
                $loadedAny = true;
            }
        }

        return $loadedAny;
    }
}
