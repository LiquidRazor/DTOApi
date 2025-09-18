<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Lib\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class DtoApiProperty
{

    /**
     * Constructor for DtoApiProperty attribute that configures property-level API documentation and validation.
     *
     * @param string|null $name Internal name of the property (defaults to property name if null)
     * @param string|null $serializedName Name to use in serialized format (wire name)
     * @param string|null $description Human-readable description of the property
     * @param string|null $type Override for property type (string|integer|number|boolean|array|object)
     * @param string|null $format Specific format of the field (date-time|email|uuid|uri|int64|float)
     * @param bool|null $nullable Whether the property can be null
     * @param string|null $itemsType For arrays: primitive type of array items
     * @param string|null $itemsRef For arrays: FQCN schema reference for complex items
     * @param array|null $enum List of allowed values for the property
     * @param string|null $enumClass FQCN of PHP enum to use for validation
     * @param int|null $minLength Minimum string length
     * @param int|null $maxLength Maximum string length
     * @param string|null $pattern Regular expression pattern for validation
     * @param float|null $minimum Minimum numeric value
     * @param float|null $maximum Maximum numeric value
     * @param bool|null $exclusiveMinimum Whether minimum value is exclusive
     * @param bool|null $exclusiveMaximum Whether maximum value is exclusive
     * @param float|null $multipleOf Number must be multiple of this value
     * @param int|null $minItems Minimum number of array items
     * @param int|null $maxItems Maximum number of array items
     * @param bool|null $uniqueItems Whether array items must be unique
     * @param bool|null $required Whether the property is required
     * @param mixed $default Default value for the property
     * @param mixed $example Example value for documentation
     * @param array|null $examples Multiple examples for documentation
     * @param bool|null $readOnly Whether the property is read-only
     * @param bool|null $writeOnly Whether the property is write-only
     * @param bool|null $deprecated Whether the property is deprecated
     * @param string|null $deprecationReason Reason for deprecation
     * @param int|null $order Hint for property ordering in schema
     * @param array|null $x Custom vendor extensions (x-* properties)
     */
    public function __construct(
        // Docs / identity
        public ?string $name = null,
        public ?string $serializedName = null,
        public ?string $description = null,
        public ?string $type = null,
        public ?string $format = null,
        public ?bool   $nullable = false,
        public ?string $itemsType = null,
        public ?string $itemsRef = null,
        public ?array  $enum = null,
        public ?string $enumClass = null,
        public ?int    $minLength = null,
        public ?int    $maxLength = null,
        public ?string $pattern = null,
        public ?float  $minimum = null,
        public ?float  $maximum = null,
        public ?bool   $exclusiveMinimum = null,
        public ?bool   $exclusiveMaximum = null,
        public ?float  $multipleOf = null,
        public ?int    $minItems = null,
        public ?int    $maxItems = null,
        public ?bool   $uniqueItems = null,
        public ?bool   $required = null,
        public mixed   $default = null,
        public mixed   $example = null,
        public ?array  $examples = null,
        public ?bool   $readOnly = null,
        public ?bool   $writeOnly = null,
        public ?bool   $deprecated = null,
        public ?string $deprecationReason = null,
        public ?int    $order = null,
        public ?array  $x = null
    ) {}
}
