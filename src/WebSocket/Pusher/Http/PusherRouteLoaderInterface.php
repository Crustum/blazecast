<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http;

use Crustum\BlazeCast\WebSocket\Http\PusherRouteBuilder;
use Symfony\Component\Routing\RouteCollection;

/**
 * PusherRouteLoaderInterface
 *
 * Interface for loading Pusher HTTP API routes
 */
interface PusherRouteLoaderInterface
{
    /**
     * Register routes with the given route builder
     *
     * Implementations should use the route builder to register
     * HTTP routes for the Pusher protocol.
     *
     * @param \Crustum\BlazeCast\WebSocket\Http\PusherRouteBuilder $builder Route builder
     * @return void
     */
    public function registerRoutes(PusherRouteBuilder $builder): void;

    /**
     * Get the route collection with registered routes
     *
     * @return \Symfony\Component\Routing\RouteCollection
     */
    public function getRouteCollection(): RouteCollection;
}
