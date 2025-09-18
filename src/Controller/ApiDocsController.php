<?php
declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment as Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final readonly class ApiDocsController
{
    public function __construct(
        private Twig                  $twig,
        private UrlGeneratorInterface $urls,
        private string                $schemaRoute = 'dtoapi_openapi_json',   // adjust if you renamed it
        private string                $title = 'LiquidRazor DTO API Docs'
    ) {}

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    #[Route(path: '/_docs', name: 'dtoapi_docs_index', methods: ['GET'])]
    public function index(): Response
    {
        return new Response($this->twig->render('@DtoApi/Docs/index.html.twig', [
            'title' => $this->title,
        ]));
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    #[Route(path: '/_docs/swagger', name: 'docs_swagger', methods: ['GET'])]
    public function swagger(): Response
    {
        $schemaUrl = $this->urls->generate($this->schemaRoute, [], UrlGeneratorInterface::ABSOLUTE_URL);

        return new Response($this->twig->render('@DtoApi/Docs/swagger.html.twig', [
            'title'     => $this->title . ' — Swagger',
            'schemaUrl' => $schemaUrl,
        ]));
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    #[Route(path: '/_docs/redoc', name: 'docs_redoc', methods: ['GET'])]
    public function redoc(): Response
    {
        $schemaUrl = $this->urls->generate($this->schemaRoute, [], UrlGeneratorInterface::ABSOLUTE_URL);

        return new Response($this->twig->render('@DtoApi/Docs/redoc.html.twig', [
            'title'     => $this->title . ' — Redoc',
            'schemaUrl' => $schemaUrl,
        ]));
    }
}
