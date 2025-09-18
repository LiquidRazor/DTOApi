<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Includes\Contracts;

/**
 * Maps between arrays (API payloads) and DTO objects.
 */
interface DtoMapperInterface
{
    /** @param array<string, mixed> $data */
    public function toDto(string $dtoClass, array $data): object;


    /** @return array<string, mixed> */
    public function fromDto(object $dto): array;
}