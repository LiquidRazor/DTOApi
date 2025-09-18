<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    // Import ALL controllers in the bundle that use #[Route] attributes
    $routes->import('../../Controller/', 'attribute')
        ->prefix('/')         // you can change to '/_docs' etc. if you want a common prefix
        ->namePrefix('dtoapi_');
};