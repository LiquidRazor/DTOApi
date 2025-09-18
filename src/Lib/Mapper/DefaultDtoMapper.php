<?php

namespace LiquidRazor\DtoApiBundle\Lib\Mapper;

use LiquidRazor\DtoApiBundle\Includes\Contracts\DtoMapperInterface;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class DefaultDtoMapper implements DtoMapperInterface
{
    private PropertyAccessorInterface $pa;


    public function __construct()
    {
        $this->pa = PropertyAccess::createPropertyAccessor();
    }


    /**
     * @throws ReflectionException
     */
    public function toDto(string $dtoClass, array $data): object
    {
        $ref = new ReflectionClass($dtoClass);
        $obj = $ref->newInstanceWithoutConstructor();


        foreach ($data as $k => $v) {
            if ($this->pa->isWritable($obj, $k)) {
                $this->pa->setValue($obj, $k, $v);
            }
        }


        return $obj;
    }


    public function fromDto(object $dto): array
    {
        $out = [];
        $ref = new ReflectionClass($dto);
        foreach ($ref->getProperties() as $prop) {
            $name = $prop->getName();
            if ($this->pa->isReadable($dto, $name)) {
                $out[$name] = $this->pa->getValue($dto, $name);
            }
        }
        return $out;
    }
}