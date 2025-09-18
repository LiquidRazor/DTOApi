<?php
declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Profiler;

use ReflectionMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;
use function is_array;
use function is_object;
use function is_string;

final class DtoApiCollector extends DataCollector
{
    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $meta = $request->attributes->get('_dtoapi.meta')
            ?? $request->attributes->get('_dtoapi')
            ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }

        // Try to resolve controller file/line/method for file_link
        [$ctrlClass, $ctrlMethod, $file, $line] = [null, null, null, null];
        $controller = $request->attributes->get('_controller'); // "App\Controller\X::method" or [obj, method]
        if (is_string($controller) && str_contains($controller, '::')) {
            [$ctrlClass, $ctrlMethod] = explode('::', $controller, 2);
        } elseif (is_array($controller) && isset($controller[0], $controller[1])) {
            $ctrlClass = is_object($controller[0]) ? $controller[0]::class : $controller[0];
            $ctrlMethod = $controller[1];
        }
        if ($ctrlClass && $ctrlMethod) {
            try {
                $r = new ReflectionMethod($ctrlClass, $ctrlMethod);
                $file = $r->getFileName() ?: null;
                $line = $r->getStartLine() ?: null;
            } catch (Throwable) { /* ignore */
            }
        }

        // Safe defaults for meta fields the template reads
        $meta += [
            'summary' => null,
            'description' => null,
            'tag' => null,
            'deprecated' => false,
            'request' => null,
            'responses' => [],
            'controller' => $ctrlClass,
            'method' => $ctrlMethod,
            'file' => $file,
            'line' => $line,
        ];

        $this->data = [
            'meta' => $meta,
            'invalid' => (bool)$request->attributes->get('_dtoapi.request_invalid', false),
            'error' => $request->attributes->get('_dtoapi.request_error') ?: null,
            'hasDto' => $request->attributes->has('_dtoapi.request_dto'),
            'request_violations' => $request->attributes->get('_dtoapi.request_violations') ?: [],
            'selectedResponse' => $request->attributes->get('_dtoapi.response_selected')
        ];
    }

    public function getName(): string
    {
        return 'dto_api';
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
