<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\OpenApi\Schema;

use LiquidRazor\DtoApiBundle\Lib\Attributes\DtoApiProperty;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Reflector;

final class DtoSchemaFactory
{
    /** @var array<class-string, array> */
    private array $cache = [];

    /** Build a JSON Schema object (OpenAPI 3.1) for a DTO class.
     * @throws ReflectionException
     */
    public function build(string $class): array
    {
        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        $rc = new ReflectionClass($class);
        $propsSchema = [];
        $required = [];

        // Maintain optional custom ordering
        $rps = $rc->getProperties();
        usort($rps, function(ReflectionProperty $a, ReflectionProperty $b) {
            $ma = $this->attr($a, DtoApiProperty::class);
            $mb = $this->attr($b, DtoApiProperty::class);
            $oa = $ma?->order ?? PHP_INT_MAX;
            $ob = $mb?->order ?? PHP_INT_MAX;
            return $oa <=> $ob;
        });

        foreach ($rps as $rp) {
            /** @var DtoApiProperty|null $meta */
            $meta = $this->attr($rp, DtoApiProperty::class);
            if (!$meta) { continue; }

            $wire = $meta->serializedName ?: ($meta->name ?: $rp->getName());
            $schema = $this->propertySchema($rp, $meta);
            $propsSchema[$wire] = $schema;

            // "required" means presence in the payload; we mark it as required,
            // even if nullable=true (presence and null allowed).
            if ($meta->required === true) {
                $required[] = $wire;
            }
        }

        $out = [
            'type'       => 'object',
            'properties' => (object)$propsSchema,
        ];
        if ($required !== []) {
            $out['required'] = array_values(array_unique($required));
        }

        // Allow extra metadata hooks
        $this->cache[$class] = $out;
        return $out;
    }

    private function propertySchema(ReflectionProperty $rp, DtoApiProperty $m): array
    {
        // Base type/format mapping
        $type   = $m->type ?: $this->inferType($rp);
        $format = $m->format;

        $schema = [];
        if ($type)  { $schema['type'] = $type; }
        if ($format){ $schema['format'] = $format; }
        if ($m->description) { $schema['description'] = $m->description; }
        if ($m->deprecated === true) { $schema['deprecated'] = true; }
        if ($m->readOnly !== null)  { $schema['readOnly'] = $m->readOnly; }
        if ($m->writeOnly !== null) { $schema['writeOnly'] = $m->writeOnly; }
        if ($m->example !== null)   { $schema['example'] = $m->example; }
        if ($m->examples)           { $schema['examples'] = $m->examples; }
        if ($m->default !== null)   { $schema['default'] = $m->default; }

        // Nullable in OAS 3.1: we can use JSON Schema union types
        if ($m->nullable === true) {
            // If a primitive type is known, use ["null", "<type>"]
            if (isset($schema['type']) && is_string($schema['type'])) {
                $schema['type'] = ['null', $schema['type']];
            } else {
                $schema['nullable'] = true; // fallback
            }
        }

        // String validators
        if ($type === 'string') {
            if ($m->minLength !== null) { $schema['minLength'] = $m->minLength; }
            if ($m->maxLength !== null) { $schema['maxLength'] = $m->maxLength; }
            if ($m->pattern    !== null){ $schema['pattern']   = $m->pattern; }
        }

        // Number validators
        if ($type === 'integer' || $type === 'number') {
            if ($m->minimum !== null) {
                $schema['minimum'] = $m->minimum;
                if ($m->exclusiveMinimum === true) { $schema['exclusiveMinimum'] = true; }
            }
            if ($m->maximum !== null) {
                $schema['maximum'] = $m->maximum;
                if ($m->exclusiveMaximum === true) { $schema['exclusiveMaximum'] = true; }
            }
            if ($m->multipleOf !== null) {
                $schema['multipleOf'] = $m->multipleOf;
            }
        }

        // Enums
        if (is_array($m->enum) && $m->enum !== []) {
            $schema['enum'] = array_values($m->enum);
        } elseif ($m->enumClass && enum_exists($m->enumClass)) {
            $schema['enum'] = array_map(static fn($c) => $c->value ?? $c->name, $m->enumClass::cases());
            if (!isset($schema['type'])) { $schema['type'] = 'string'; }
        }

        // Arrays
        if ($type === 'array') {
            $items = [];
            if ($m->itemsType) {
                $items['type'] = $m->itemsType;
            }
            if ($m->itemsRef) {
                $items = ['$ref' => $this->refFor($m->itemsRef)];
            }
            if ($items !== []) {
                $schema['items'] = $items;
            }
            if ($m->minItems !== null) { $schema['minItems'] = $m->minItems; }
            if ($m->maxItems !== null) { $schema['maxItems'] = $m->maxItems; }
            if ($m->uniqueItems)       { $schema['uniqueItems'] = true; }
        }

        // Vendor extensions (x-*)
        if (is_array($m->x)) {
            foreach ($m->x as $k => $v) {
                if (!str_starts_with((string)$k, 'x-')) continue;
                $schema[$k] = $v;
            }
        }

        return $schema;
    }

    private function inferType(ReflectionProperty $rp): ?string
    {
        $t = $rp->getType();
        if (!$t) return null;
        $n = (string) $t;
        return match ($n) {
            'string','?string' => 'string',
            'int','?int'       => 'integer',
            'float','?float'   => 'number',
            'bool','?bool'     => 'boolean',
            'array','?array'   => 'array',
            default            => 'object',
        };
    }

    private function attr(Reflector $r, string $attr): ?object
    {
        $a = $r->getAttributes($attr);
        return $a ? $a[0]->newInstance() : null;
    }

    private function refFor(string $class): string
    {
        return '#/components/schemas/' . $this->schemaName($class);
    }

    public function schemaName(string $class): string
    {
        // Short class name is fine; if collisions, maybe add namespaces later
        $parts = explode('\\', $class);
        return end($parts);
    }
}
