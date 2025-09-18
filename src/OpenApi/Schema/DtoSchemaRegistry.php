<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\OpenApi\Schema;

use LiquidRazor\DtoApiBundle\Lib\Attributes\DtoApiProperty;
use ReflectionClass;
use ReflectionProperty;

final class DtoSchemaRegistry
{
    /** @var array<class-string, array> */
    private array $schemas = [];

    public function __construct(private readonly DtoSchemaFactory $factory) {}

    /** Ensure schema for class exists (and recursively ensure its dependencies).
     * @throws \ReflectionException
     */
    public function ensure(string $class): void
    {
        if (isset($this->schemas[$class])) {
            return;
        }

        // Build and store the class schema
        $this->schemas[$class] = $this->factory->build($class);

        // Crawl properties for nested refs (itemsRef for arrays, object refs later)
        $rc = new ReflectionClass($class);
        foreach ($rc->getProperties() as $rp) {
            $meta = $this->attr($rp, DtoApiProperty::class);
            if (!$meta) { continue; }

            if ($meta->itemsRef && \class_exists($meta->itemsRef)) {
                $this->ensure($meta->itemsRef);
            }
            // If you later add plain object refs (e.g., $meta->ref), handle here too.
        }
    }

    /** Export components/schemas object ready to put into OpenAPI doc */
    public function export(): object
    {
        $out = new \stdClass();
        foreach ($this->schemas as $class => $schema) {
            $out->{$this->factory->schemaName($class)} = $schema;
        }
        return $out;
    }

    private function attr(\Reflector $r, string $attr): ?object
    {
        $a = $r->getAttributes($attr);
        return $a ? $a[0]->newInstance() : null;
    }
}
