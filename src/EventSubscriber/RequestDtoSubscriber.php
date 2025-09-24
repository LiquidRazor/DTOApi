<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\EventSubscriber;

use LiquidRazor\DtoApiBundle\Lib\Attributes\DtoApi;
use LiquidRazor\DtoApiBundle\Lib\Attributes\DtoApiOperation;
use LiquidRazor\DtoApiBundle\Lib\Attributes\DtoApiResponse;
use LiquidRazor\DtoApiBundle\Lib\Response\ResponseMappingResolver;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

readonly class RequestDtoSubscriber implements EventSubscriberInterface
{

    public function __construct(
        private SerializerInterface $serializer,
        private ValidatorInterface  $validator,
        private ResponseMappingResolver $responseMappingResolver,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => ['onController', 50]];
    }

    /**
     * @throws ReflectionException
     */
    public function onController(ControllerEvent $event): void
    {
        $controller = $event->getController();
        if (is_array($controller)) {
            [$obj, $method] = $controller;
        } elseif (is_object($controller) && method_exists($controller, '__invoke')) {
            $obj = $controller;
            $method = '__invoke';
        } else {
            return;
        }

        $rc = new ReflectionClass($obj);
        $rm = $rc->getMethod($method);

        $opAttr = $rm->getAttributes(DtoApiOperation::class, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        if (!$opAttr) {
            return;
        }
        $op = $opAttr->newInstance();

        // collect method-level responses (for profiler; optional here)
        $responses = array_map(
            static fn($a) => $a->newInstance(),
            $rm->getAttributes(DtoApiResponse::class, ReflectionAttribute::IS_INSTANCEOF)
        );
        $req = $event->getRequest();

        $methodLevel = array_map(fn($a) => (array)$a, $responses);
        $opLevel     = is_array($op->response) ? $op->response : (array)$op->response;

        $resolvedResponses = $this->responseMappingResolver->resolve($methodLevel, $opLevel);

        $req->attributes->set('_dtoapi.meta', [
            'controller' => $rc->getName(),
            'method' => $rm->getName(),
            'tag' => ($rc->getAttributes(DtoApi::class)[0] ?? null)?->newInstance()?->name
                ?? $op->tag ?? $rc->getShortName(),
            'summary' => $op->summary,
            'description' => $op->description,
            'request' => $op->request,
            'responses' => array_map(static fn($r) => [
                'class' => $r['class'],
                'status' => $r['status'],
                'contentType' => $r['contentType'],
                'stream' => $r['stream'],
                'name' => $r['name'],
                'description' => $r['description'],
            ], $resolvedResponses),
            'deprecated' => $op->deprecated,
        ]);

        // hydrate requests DTO here (so the ValueResolver can pick it up)
        if (is_string($op->request) && class_exists($op->request)) {
            $this->hydrateRequestDto($req, $op->request);
        }
    }

    private function hydrateRequestDto(Request $req, string $dtoClass): void
    {
        $content = $req->getContent();
        if ($content === '' && $req->getMethod() !== Request::METHOD_GET) {
            $req->attributes->set('_dtoapi.request_empty', true);
            return;
        }

        if ($content === '' && $req->getMethod() === Request::METHOD_GET) {
            $content = $req->query->all();
        }

        $format = 'json';
        try {
            $dto = match (true) {
                \is_string($content) => $this->serializer->deserialize($content, $dtoClass, $format),
                \is_array($content) => $this->serializer->denormalize($content, $dtoClass, $format),
            };
            $validationErrors = $this->validator->validate($dto);

            if (count($validationErrors) > 0) {
                $req->attributes->set('_dtoapi.request_invalid', true);
                // store simplified violations so the profiler template can render them
                $req->attributes->set('_dtoapi.request_violations',iterator_to_array($validationErrors));
            }
            $req->attributes->set('_dtoapi.request_dto', $dto);
        } catch (Throwable $e) {
            $req->attributes->set('_dtoapi.request_error', [
                'class' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}