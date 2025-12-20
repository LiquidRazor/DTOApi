<?php
declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\EventSubscriber;

use JsonException;
use LiquidRazor\DtoApiBundle\Includes\Contracts\ResponseDTO;
use LiquidRazor\DtoApiBundle\Lib\Normalizer\PreDtoNormalizer;
use LiquidRazor\DtoApiBundle\Lib\Streaming\NdjsonStreamer;
use LiquidRazor\DtoApiBundle\Lib\Streaming\SseStreamer;
use LogicException;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class ResponseDtoSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SerializerInterface $serializer,
        private SseStreamer         $sseStreamer,
        private NdjsonStreamer      $ndjsonStreamer
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

        $result = $event->getControllerResult();

        // If the controller returned a real Response, leave it alone.
        if ($result instanceof Response) {
            return;
        }

        // Find the best response mapping (Option B)
        $mapping = $this->pickResponseMapping($meta['responses'] ?? [], $result);

        $request->attributes->set('_dtoapi.response_selected', [
            'status' => $mapping['status'] ?? 200,
            'contentType' => $mapping['contentType'] ?? 'application/json',
            'stream' => (bool)($mapping['stream'] ?? false),
            'class' => $mapping['class'] ?? null,
            'source' => 'view',           // mark where it was chosen
        ]);

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

        if (!is_subclass_of($mappingClass, ResponseDTO::class)) {
            throw new LogicException("DTO class '$mappingClass' must implement ResponseDTO.");
        }

        $json = $this->serializer->serialize($mappingClass::fromControllerResponse($result), 'json');
        $event->setResponse(new Response($json, $mapping['status'] ?? 200, [
            'Content-Type' => $mapping['contentType'] ?? 'application/json'
        ]));
    }

    private function pickResponseMapping(array $responses, mixed $result): array
    {
        // 1. Exact class match
        foreach ($responses as $response) {
            if (!empty($response['class']) && is_object($result) && is_a($result, $response['class'])) {
                return $response;
            }
        }

        // 2. Explicit 2xx/3xx mapping (success/redirect)
        foreach ($responses as $response) {
            $status = (int)($response['status'] ?? 200);
            if ($status >= 200 && $status < 400) {
                return $response;
            }
        }

        // 3. First non-error mapping (< 400) - redundant given step 2, but following instructions "first non-error mapping"
        foreach ($responses as $response) {
            $status = (int)($response['status'] ?? 200);
            if ($status < 400) {
                return $response;
            }
        }

        // 4. Finally use the first declared mapping
        return $responses[0] ?? ['status' => 200, 'contentType' => 'application/json'];
    }
}
