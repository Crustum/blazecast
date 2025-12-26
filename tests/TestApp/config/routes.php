<?php
/**
 * Routes configuration for TestApp
 *
 * This file defines the routes used during testing.
 */

use Cake\Routing\RouteBuilder;

return static function (RouteBuilder $routes): void {
    $routes->setRouteClass('DashedRoute');

    $routes->scope('/', function (RouteBuilder $builder): void {

        $builder->fallbacks('DashedRoute');
    });
};
