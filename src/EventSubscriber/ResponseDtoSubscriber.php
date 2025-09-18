<?php
declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\EventSubscriber;

use LiquidRazor\DtoApiBundle\Lib\Streaming\NdjsonStreamer;
use LiquidRazor\DtoApiBundle\Lib\Streaming\SseStreamer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ResponseDtoSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SerializerInterface $serializer,
        private ValidatorInterface  $validator,
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
            // streaming expects an iterable; allow single object and wrap it
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
            'status'      => $mapping['status'] ?? 200,
            'contentType' => $mapping['contentType'] ?? 'application/json',
            'stream'      => (bool)($mapping['stream'] ?? false),
            'class'       => $mapping['class'] ?? null,
            'source'      => 'view',           // mark where it was chosen
        ]);

        // 2) No body (e.g., 204)
        if ($result === null && ($mapping['class'] ?? null) === null) {
            $event->setResponse(new Response('', $mapping['status'] ?? 204));
            return;
        }

        // 3) DTO object → validate & serialize
        if (is_object($result)) {
            $violations = $this->validator->validate($result);
            if (count($violations) > 0) {
                $payload = $this->buildResponseViolationPayload($violations);
                $json = $this->serializer->serialize($payload, 'json');
                $event->setResponse(new Response($json, 500, ['Content-Type' => 'application/json']));
                return;
            }

            if (!empty($mapping['stream'])) {
                $event->setResponse(new StreamedResponse(function () use ($result) {
                    // naive NDJSON; refine later
                    echo $this->serializer->serialize($result, 'json') . "\n";
                    flush();
                }, $mapping['status'] ?? 200, [
                    'Content-Type' => $mapping['contentType'] ?? 'application/x-ndjson'
                ]));
                return;
            }

            $format = ($mapping['contentType'] ?? 'application/json');
            $body = $this->serializer->serialize($result, 'json');
            $event->setResponse(new Response($body, $mapping['status'] ?? 200, ['Content-Type' => $format]));
        }
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

    private function buildResponseViolationPayload(ConstraintViolationListInterface $violations): array
    {
        return [
            'type' => 'about:blank',
            'title' => 'Response validation failed.',
            'status' => 422,
            'violations' => array_map(
                fn(ConstraintViolationInterface $violation) => ['property' => $violation->getPropertyPath(), 'message' => $violation->getMessage()],
                iterator_to_array($violations)
            ),
        ];
    }
}
