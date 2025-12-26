<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Exception;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;

/**
 * Auth Controller
 *
 * Handles POST /pusher/auth endpoint for private and presence channel authentication.
 *
 * @phpstan-type RouteParams array<string, mixed>
 * @phpstan-type FormData array<string, mixed>
 * @phpstan-type AuthData array<string, mixed>
 * @phpstan-type AuthResult array<string, mixed>
 */
class AuthController extends PusherController
{
    /**
     * Handle request
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection HTTP connection
     * @param RouteParams $params Route parameters
     * @return \Crustum\BlazeCast\WebSocket\Http\Response
     */
    public function handle(RequestInterface $request, Connection $connection, array $params): Response
    {
        return $this->auth($request);
    }

    /**
     * Handle POST /pusher/auth
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @return \Crustum\BlazeCast\WebSocket\Http\Response
     */
    public function auth(RequestInterface $request): Response
    {
        try {
            if ($request->getMethod() === 'OPTIONS') {
                return $this->handleOptions();
            }

            $data = $this->parseFormData($request);

            $this->validateAuthData($data);

            $authResult = $this->authenticateChannel($data);

                BlazeCastLogger::info('Channel auth request processed', [
                'scope' => ['socket.controller', 'socket.controller.auth'],
                'channel' => $data['channel_name'],
                'socket_id' => $data['socket_id'],
                'success' => isset($authResult['auth']),
                ]);

            return $this->jsonResponse($authResult);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (Exception $e) {
                BlazeCastLogger::error('Error processing channel auth', [
                'scope' => ['socket.controller', 'socket.controller.auth'],
                'error' => $e->getMessage(),
                ]);

            return $this->errorResponse('Authentication failed', 403);
        }
    }

    /**
     * Parse form-encoded data from request
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @return FormData Parsed form data
     */
    protected function parseFormData(RequestInterface $request): array
    {
        $body = (string)$request->getBody();
        parse_str($body, $data);

        return $data;
    }

    /**
     * Validate authentication data
     *
     * @param AuthData $data Form data
     * @return void
     * @throws \InvalidArgumentException If validation fails
     */
    protected function validateAuthData(array $data): void
    {
        $required = ['socket_id', 'channel_name'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!preg_match('/^(private-|presence-)/', $data['channel_name'])) {
            throw new InvalidArgumentException('Channel authentication only for private and presence channels');
        }
    }

    /**
     * Authenticate channel subscription
     *
     * @param AuthData $data Auth data
     * @return AuthResult Authentication result
     * @throws \InvalidArgumentException If authentication fails
     */
    protected function authenticateChannel(array $data): array
    {
        $channelName = $data['channel_name'];
        $socketId = $data['socket_id'];

        $apps = $this->applicationManager->getApplications();
        if (empty($apps)) {
            throw new InvalidArgumentException('No applications configured');
        }

        $app = reset($apps);

        if (str_starts_with($channelName, 'private-')) {
            $authString = "{$socketId}:{$channelName}";
            $signature = hash_hmac('sha256', $authString, $app['secret']);

            return [
                'auth' => "{$app['key']}:{$signature}",
            ];
        }

        if (str_starts_with($channelName, 'presence-')) {
            $userData = $data['channel_data'] ?? json_encode([
                'user_id' => $socketId,
                'user_info' => [],
            ]);

            $authString = "{$socketId}:{$channelName}:{$userData}";
            $signature = hash_hmac('sha256', $authString, $app['secret']);

            return [
                'auth' => "{$app['key']}:{$signature}",
                'channel_data' => $userData,
            ];
        }

        throw new InvalidArgumentException('Invalid channel type for authentication');
    }

    /**
     * Invoke the controller
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param RouteParams $params Route parameters
     * @return \Crustum\BlazeCast\WebSocket\Http\Response HTTP response
     */
    public function __invoke(RequestInterface $request, Connection $connection, array $params = []): Response
    {
        try {
                BlazeCastLogger::info('AuthController: Invoked for channel authentication', [
                'scope' => ['socket.controller', 'socket.controller.auth'],
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
                ]);

            $this->query = $this->parseQueryParams($request);
            $this->body = $this->parseRequestBody($request);
            $this->requestData = [
                'query' => $this->query,
                'body' => $this->body,
                'params' => $params,
            ];

                BlazeCastLogger::info('AuthController: Request data parsed', [
                'scope' => ['socket.controller', 'socket.controller.auth'],
                'query' => $this->query,
                'body_length' => $this->body ? strlen($this->body) : 0,
                ]);

            if ($request->getMethod() === 'OPTIONS') {
                BlazeCastLogger::info('AuthController: Handling OPTIONS request', [
                    'scope' => ['socket.controller', 'socket.controller.auth'],
                ]);

                return $this->handleOptions();
            }

            $response = $this->handle($request, $connection, $params);

                BlazeCastLogger::info('AuthController: Channel authentication handled successfully', [
                'scope' => ['socket.controller', 'socket.controller.auth'],
                'status_code' => $response->getStatusCode(),
                'response_size' => strlen($response->getContent()),
                ]);

            return $response;
        } catch (Exception $e) {
                BlazeCastLogger::error('AuthController: Error processing channel authentication' . "\n" . $e->getMessage(), [
                'scope' => ['socket.controller', 'socket.controller.auth'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                ]);

            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
