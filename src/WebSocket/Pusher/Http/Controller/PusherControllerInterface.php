<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Psr\Http\Message\RequestInterface;

/**
 * PusherControllerInterface
 *
 * Interface for Pusher HTTP API controllers
 *
 * @phpstan-type RouteParams array<string, mixed>
 * @phpstan-type QueryParams array<string, mixed>
 * @phpstan-type RequestData array<string, mixed>
 * @phpstan-type PayloadData array<string, mixed>
 * @phpstan-type ResponseData array<string, mixed>|object|null
 * @phpstan-type ResponseHeaders array<string, string>
 * @phpstan-type CustomMessages array<string, string>
 * @phpstan-type FieldTypes array<string, string>
 * @phpstan-type LogOptions array<string, mixed>
 */
interface PusherControllerInterface
{
    /**
     * Invoke the controller
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param RouteParams $params Route parameters
     * @return \Crustum\BlazeCast\WebSocket\Http\Response HTTP response
     */
    public function __invoke(RequestInterface $request, Connection $connection, array $params = []): Response;

    /**
     * Handle the request
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param RouteParams $params Route parameters
     * @return \Crustum\BlazeCast\WebSocket\Http\Response HTTP response
     */
    public function handle(RequestInterface $request, Connection $connection, array $params): Response;
}
