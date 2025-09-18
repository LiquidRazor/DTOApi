<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\OpenApi;

use LiquidRazor\DtoApiBundle\Lib\Attributes\{DtoApi, DtoApiOperation, DtoApiResponse, DtoApiRequest};
use JsonException;
use LiquidRazor\DtoApiBundle\OpenApi\Schema\{DtoSchemaFactory, DtoSchemaRegistry};
use LiquidRazor\DtoApiBundle\Lib\Response\ResponseMappingResolver;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Route;

final readonly class OpenApiBuilder
{
    public function __construct(
        private RouterInterface  $router,
        private DtoSchemaFactory $schemas,
        private DtoSchemaRegistry $registry,
        private ResponseMappingResolver $responseMappingResolver,
        private string           $title = 'API',
        private string           $version = '0.1.0',
    ) {}

    /** Build the full OpenAPI 3.1 document as an array
     * @throws ReflectionException|JsonException
     */
    public function build(): array
    {
        $paths = [];
        $components = ['schemas' => (object)[]];
        $tags = [];

        foreach ($this->router->getRouteCollection() as $route) {
            $controller = $route->getDefault('_controller') ?? null;
            if (!$controller || !is_string($controller)) continue;

            [$class, $method] = $this->parseCallable($controller);
            if (!$class || !$method) continue;
            try {
                $rc = new ReflectionClass($class);
            } catch (ReflectionException) {
                continue;
            }
            if (!$rc->isInstantiable()) continue;
            if (!$rc->hasMethod($method)) continue;
            $rm = $rc->getMethod($method);

            $opAttr = $rm->getAttributes(DtoApiOperation::class, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
            if (!$opAttr) continue; // not a DtoApi operation

            /** @var DtoApiOperation $op */
            $op = $opAttr->newInstance();
            $path = $this->toOpenApiPath($route);

            $httpMethods = $route->getMethods() ?: ['GET'];
            foreach ($httpMethods as $http) {
                $http = strtolower($http);
                $operation = $this->operationObject($rc, $rm, $op, $components, $tags);
                $paths[$path][$http] = $operation;
            }
        }

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->title,
                'version' => $this->version,
            ],
            'paths' => $this->objectify($paths),
            'components' => ['schemas' => $this->registry->export()],
//            'components' => $components,
            'tags' => array_values($tags),
        ];
    }

    /**
     * @throws ReflectionException
     * @throws JsonException
     */
    private function operationObject(ReflectionClass $rc, ReflectionMethod $rm, DtoApiOperation $op, array &$components, array &$tags): array
    {
        // Tags: prefer DtoApi on class, else attribute tag, else short class
        $classMeta = ($rc->getAttributes(DtoApi::class)[0] ?? null)?->newInstance();
        $tagName = $op->tag ?? $classMeta?->name ?? $rc->getShortName();

        $tags[$tagName] ??= ['name' => $tagName];

        $operationId = $rc->getShortName().'::'.$rm->getName();

        $out = [
            'operationId' => $operationId,
            'summary'     => $op->summary,
            'description' => $op->description,
            'tags'        => [$tagName]
        ];

        // Request body
        if (is_string($op->request) && class_exists($op->request)) {
            $this->registry->ensure($op->request);
            $schemaName = $this->schemas->schemaName($op->request);
            $components['schemas']->{$schemaName} = $this->schemas->build($op->request);
            // default content type
            $contentType = 'application/json';
            $reqMeta = (new ReflectionClass($op->request))->getAttributes(DtoApiRequest::class)[0] ?? null;
            if ($reqMeta) {
                $ct = $reqMeta->newInstance()->contentType ?? null;
                if ($ct) { $contentType = $ct; }
            }
            $out['requestBody'] = [
                'required' => true, // you can refine by checking properties marked required
                'content' => [
                    $contentType => [
                        'schema' => ['$ref' => '#/components/schemas/'.$schemaName]
                    ]
                ]
            ];
        }

//        // Response from method-level DtoApiResponse (Option B)
//        $responses =

        $methodLevel = array_map(
            static fn($a) => $a->newInstance(),
            $rm->getAttributes(DtoApiResponse::class, ReflectionAttribute::IS_INSTANCEOF)
        );;
        $opLevel     = is_array($op->response) ? $op->response : (array)$op->response;

        $responses = $this->responseMappingResolver->resolve($methodLevel, $opLevel);

//        // Fallback to operation.response/classes if not declared
//        if ($responses === [] && $op->response !== null) {
//            $classes = is_array($op->response) ? $op->response : [$op->response];
//
//            $statuses = $op->status ?: [200];
//            foreach ($classes as $cls) {
//                foreach ($statuses as $st) {
//                    $responses[] = new DtoApiResponse(
//                        status: (int)$st,
//                        class: is_string($cls) ? $cls : null
//                    );
//                }
//            }
//        }

        // Build responses section
        $respObj = [];
        foreach ($responses as $r) {
            $status = (string)$r['status'];
            $desc = $r->description ?? ($r->name ?? '');
            $contentType = $r->contentType ?? 'application/json';

            $content = [];
            if ($r['class']) {
                $this->registry->ensure($r['class']);
                $schemaName = $this->schemas->schemaName($r['class']);
                $components['schemas']->{$schemaName} ??= $this->schemas->build($r['class']);
                $content[$contentType] = [
                    'schema' => ['$ref' => '#/components/schemas/'.$schemaName]
                ];
            }

            // streaming hint
            if ($r['stream'] && $contentType === 'application/json') {
                $contentType = 'application/x-ndjson';
            }

            $resp = ['description' => $desc ?: Response::$statusTexts[$r['status']]];
            if ($content !== []) {
                $resp['content'] = $content;
            }
            $resp['content-type'] = $contentType;

            $respObj[$status] = $resp;
        }

        // Ensure at least a generic 200 if nothing found
        if ($respObj === []) {
            $respObj['200'] = ['description' => 'OK'];
        }

        $out['responses'] = $this->objectify($respObj);
        return $out;
    }

    private function toOpenApiPath(Route $route): string
    {
        // Convert /users/{id} style – Symfony routes already use {}
        $path = $route->getPath();
        return $path === '' ? '/' : $path;
    }

    private function parseCallable(string $controller): array
    {
        // Formats: 'App\Controller\X::method' or invokable 'App\Controller\X'
        if (str_contains($controller, '::')) {
            return explode('::', $controller, 2);
        }
        if (class_exists($controller)) {
            return [$controller, '__invoke'];
        }
        return [null, null];
    }

    /**
     * @throws JsonException
     */
    private function objectify(array $arr): object
    {
        // Convert associative arrays to stdClass for cleaner JSON output
        return json_decode(json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), false, 512, JSON_THROW_ON_ERROR);
    }
}
