# LiquidRazor/DtoApiBundle

> **DTO-first API toolkit for Symfony 7.x**  
> Attributes for request/response contracts, automatic validation, safe (de)serialization, streaming (NDJSON/SSE), OpenAPI generation, and Symfony Profiler integration.

---

[![Latest Stable Version](https://poser.pugx.org/liquidrazor/dto-api-bundle/v/stable)](https://packagist.org/packages/liquidrazor/dto-api-bundle)
[![Total Downloads](https://poser.pugx.org/liquidrazor/dto-api-bundle/downloads)](https://packagist.org/packages/liquidrazor/dto-api-bundle)
[![License](https://poser.pugx.org/liquidrazor/dto-api-bundle/license)](LICENCE)

---

## ✨ Features

- **Attributes** for requests, responses, operations, and properties
- **Validation bridge**: property metadata → Symfony constraints (no YAML/XML)
- **Safe request hydration** via events (no fatals, no leaks)
- **Response mapping** with global defaults (422/500)
- **Error handling**: exceptions → mapped error DTOs
- **Streaming helpers**: NDJSON (`application/x-ndjson`) and SSE (`text/event-stream`)
- **OpenAPI 3.1/3.0.3** generator with Swagger UI + Redoc
- **Profiler panel**: metadata, DTOs, violations, and the actual response used

---

## 🧰 Requirements

- PHP **8.3+**
- Symfony **7.0+**
- packages: (already present in composer.json)
  - `ext-json`
  - `symfony/dependency-injection`
  - `symfony/config`
  - `symfony/http-kernel`
  - `symfony/serializer`
  - `symfony/property-access`
  - `symfony/options-resolver`
  - `symfony/validator`
  - `monolog/monolog` 

---

## 📦 Install

```bash
composer require liquidrazor/dto-api-bundle
```

Enable the bundle (if Flex doesn’t auto-register):

config/bundles.php

```php 
return [
    ...
    LiquidRazor\DtoApiBundle\LiquidRazorDtoApiBundle::class => ['all' => true],
];
```

## ⚙️ Configuration

### Responses

```yaml
# config/packages/liquidrazor_dto_api.yaml
liquidrazor_dto_api:
  normalizer_priority: 10
  strict_types: true
  openapi_version: '3.1.0'   # or '3.0.3' for Redoc OSS compatibility
  default_responses:
    422:
        class: LiquidRazor\DtoApiBundle\Response\ValidationErrorResponse
        description: 'Validation error'
    500:
        class: LiquidRazor\DtoApiBundle\Response\ErrorResponse
        description: 'Server error'
```

Edit the file and set up any custom response classes (and|or descriptions)

This is the default configuration and should be there if flex auto-registered the bundle. Otherwise it's probably missing and should be added.

### Routes

add the following to your routes.yaml (if not already added by symfony flex)

```yaml
# config/routes/liquidrazor_dto_api.yaml
liquidrazor_dto_api:
  resource: '@LiquidRazorDtoApiBundle/Resources/config/routes.php'

```

You now get:

- `/_schema/openapi.json` — OpenAPI spec
- `/_docs/swagger` — Swagger UI
- `/_docs/redoc` — Redoc UI

## 🚀 Usage Guides

### 1. Request DTO

```php
use LiquidRazor\DtoApiBundle\Lib\Attributes\{DtoApiRequest, DtoApiProperty};

#[DtoApiRequest(name: 'UserInput')]
final readonly class UserInputDto
{
    public function __construct(
        #[DtoApiProperty(type: 'string', required: true, minLength: 3)]
        public ?string $name = null,

        #[DtoApiProperty(type: 'integer', required: true, minimum: 18, maximum: 100)]
        public ?int $age = null,
    ) {}
}
```
Validation is automatic — missing/invalid fields trigger a 422 ValidationErrorResponse (unless you override the default response).


### 2. Response DTO

```php
use LiquidRazor\DtoApiBundle\Lib\Attributes\{DtoApiResponse, DtoApiProperty};

#[DtoApiResponse(status: 200, description: 'User created')]
final readonly class UserResponse
{
    public function __construct(
        #[DtoApiProperty(type: 'string')] public string $id,
        #[DtoApiProperty(type: 'string')] public string $name,
    ) {}
}
```

### 3. Controller

```php
use LiquidRazor\DtoApiBundle\Lib\Attributes\{DtoApi, DtoApiOperation};
use Symfony\Component\Routing\Attribute\Route;

#[DtoApi]
final class UserController
{
    #[DtoApiOperation(
        summary: 'Create user',
        description: 'Accepts a UserInputDto and returns a UserResponse',
        request: UserInputDto::class,
        response: [UserResponse::class] // 422/500 added by defaults
    )]
    #[Route('/users', methods: ['POST'])]
    public function create(UserInputDto $request): UserResponse
    {
        return new UserResponse(id: uniqid(), name: $request->name);
    }
}
```

### 4. Error Handling

All exceptions are logged

Mapped to a declared `#[DtoApiResponse(status: …)]` if present

Fallback: ErrorResponse (500 JSON)

### 5. Streaming

#### 5.1  NDJSON
```php
#[DtoApiOperation(summary: 'NDJSON counter')]
#[DtoApiResponse(status: 200, stream: true, contentType: 'application/x-ndjson')]
#[Route('/stream/ndjson', methods: ['GET'])]
public function streamNdjson(): iterable
{
    for ($i=1; $i<=5; $i++) {
        yield ['i' => $i, 'ts' => (new \DateTimeImmutable())->format(DATE_ATOM)];
        usleep(200_000);
    }
}
```
#### 5.2  SSE
```php
use LiquidRazor\DtoApiBundle\Lib\Streaming\SseEvent;

#[DtoApiOperation(summary: 'SSE clock')]
#[DtoApiResponse(status: 200, stream: true, contentType: 'text/event-stream')]
#[Route('/stream/sse', methods: ['GET'])]
public function sseClock(): iterable
{
    for ($i=0; $i<5; $i++) {
        yield new SseEvent(['now' => date(DATE_ATOM)], 'tick', (string)$i, 3000);
        sleep(1);
    }
}
```

### 6. Profiler

Symfony Profiler panel shows:

Operation metadata (summary, request/response DTOs)

Request violations (422 errors)

Which response mapping was actually used.


## 🧩 Extensibility

- Custom constraints: tag dtoapi.constraint_contributor to translate custom hints into Symfony constraints
- Global defaults: override liquidrazor_dto_api.default_responses
- OpenAPI hooks: extend components, security, servers, parameters

## 📍 Known limitations
- Very strict CSP may require self-hosting Swagger/Redoc assets instead of using CDNs

## 📝 License

MIT

## 🙌 Credits

Built by LiquidRazor with help from Symfony’s excellent components.