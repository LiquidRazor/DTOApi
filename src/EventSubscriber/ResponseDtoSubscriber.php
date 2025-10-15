<?php
declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\EventSubscriber;

use JsonException;
use LiquidRazor\DtoApiBundle\Includes\Contracts\ResponseDTO;
use LiquidRazor\DtoApiBundle\Lib\Normalizer\PreDtoNormalizer;
use LiquidRazor\DtoApiBundle\Lib\Streaming\NdjsonStreamer;
use LiquidRazor\DtoApiBundle\Lib\Streaming\SseStreamer;
use LogicException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolation;

final readonly class ResponseDtoSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SerializerInterface $serializer,
        private SseStreamer         $sseStreamer,
        private NdjsonStreamer      $ndjsonStreamer,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Run before default view listeners
        return [KernelEvents::VIEW => ['onView', 50]];
    }

    /**
     * @throws ExceptionInterface
     * @throws JsonException
     */
    public function onView(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $meta = $request->attributes->get('_dtoapi.meta');
        if (!$meta) {
            return;
        }

        // 1) Convert invalid request into error response
        if ($request->attributes->get('_dtoapi.request_invalid') || $request->attributes->has('_dtoapi.request_error')) {
            $payload = $this->buildRequestErrorPayload($request);
            $json = $this->serializer->serialize($payload, 'json');
            $event->setResponse(new Response($json, 422, ['Content-Type' => 'application/json']));
            return;
        }

        $result = $event->getControllerResult();

        // If the controller returned a real Response, leave it alone.
        if ($result instanceof Response) {
            return;
        }

        // Find the best response mapping (Option B)
        $mapping = $this->pickResponseMapping($meta['responses'] ?? [], $result);

        if (!empty($mapping['stream'])) {
            // streaming expects an iterable object; allow a single object and wrap it
            $iterable = is_iterable($result) ? $result : [$result];

            $ct = $mapping['contentType'] ?? 'application/x-ndjson';

            if (str_starts_with($ct, 'text/event-stream')) {
                // SSE
                $resp = $this->sseStreamer->stream($iterable, $mapping['status'] ?? 200);
                $event->setResponse($resp);
                return;
            }

            $resp = $this->ndjsonStreamer->stream($iterable, $mapping['status'] ?? 200);
            $event->setResponse($resp);
            return;
        }

        $request->attributes->set('_dtoapi.response_selected', [
            'status' => $mapping['status'] ?? 200,
            'contentType' => $mapping['contentType'] ?? 'application/json',
            'stream' => (bool)($mapping['stream'] ?? false),
            'class' => $mapping['class'] ?? null,
            'source' => 'view',           // mark where it was chosen
        ]);

        // 2) No body (e.g., 204)
        if ($result === null && ($mapping['class'] ?? null) === null) {
            $event->setResponse(new Response('', $mapping['status'] ?? 204));
            return;
        }

        $mappingClass = $mapping['class'] ?? null;

        if ($mappingClass === null) {
            $pre = (new PreDtoNormalizer([
                'asStdClass' => true,
                'deep' => true,
                'maxDepth' => 8,
            ]))->normalize($result);
            $json = $this->serializer->serialize($pre, 'json');
            $event->setResponse(new Response($json, $mapping['status'] ?? 200, [
                'Content-Type' => $mapping['contentType'] ?? 'application/json'
            ]));
            return;
        }

        if (class_exists($mappingClass) && method_exists($mappingClass, 'customTransform')) {
            $mapper = new $mappingClass();
            $payload = $mapper->customTransform($request);
            $json = $this->serializer->serialize($payload, 'json');
            $event->setResponse(new Response($json, $mapping['status'] ?? 200, [
                'Content-Type' => $mapping['contentType'] ?? 'application/json'
            ]));
            return;
        }

        if (!is_subclass_of($mappingClass, ResponseDto::class)) {
            throw new LogicException("DTO class '$mappingClass' must implement ResponseDto.");
        }

        $json = $this->serializer->serialize($mappingClass::fromControllerResponse($result), 'json');
        $event->setResponse(new Response($json, $mapping['status'] ?? 200, [
            'Content-Type' => $mapping['contentType'] ?? 'application/json'
        ]));
    }

    private function buildRequestErrorPayload(Request $request): array
    {
        if ($validationErrors = $request->attributes->get('_dtoapi.request_violations')) {
            return [
                'type' => 'Validation error',
                'title' => 'Invalid request body.',
                'status' => 422,
                'violations' => array_map(
                    fn(ConstraintViolation $validationError) => [
                        'property' => $validationError->getPropertyPath(),
                        'message' => $validationError->getMessage()
                    ],
                    iterator_to_array($validationErrors)
                ),
            ];
        }
        $requestErrorData = $request->attributes->get('_dtoapi.request_error');
        return [
            'type' => 'about:blank',
            'title' => 'Malformed request body.',
            'status' => 400,
            'detail' => $requestErrorData['message'] ?? 'Unknown error',
        ];
    }

    private function pickResponseMapping(array $responses, mixed $result): array
    {
        foreach ($responses as $response) {
            if (!empty($response['class']) && is_object($result) && is_a($result, $response['class'])) {
                return $response;
            }
        }
        return $responses[0] ?? ['status' => 200, 'contentType' => 'application/json'];
    }
}
