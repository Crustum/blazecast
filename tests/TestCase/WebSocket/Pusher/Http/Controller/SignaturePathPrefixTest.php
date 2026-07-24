<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\Core\Configure;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Signature path-prefix stripping tests (M2)
 */
class SignaturePathPrefixTest extends TestCase
{
    /**
     * Test verifySignature strips server path prefix before HMAC.
     *
     * @return void
     */
    public function testVerifySignatureStripsServerPath(): void
    {
        $method = 'POST';
        $signedPath = '/apps/1/events';
        $requestPath = '/ws/apps/1/events';
        $appSecret = 'app-secret';

        $authParams = [
            'auth_key' => 'app-key',
            'auth_timestamp' => '1750374111',
            'auth_version' => '1.0',
        ];
        ksort($authParams);
        $queryString = http_build_query($authParams);
        $clientSignature = hash_hmac('sha256', "{$method}\n{$signedPath}\n{$queryString}", $appSecret);

        Configure::write('BlazeCast.servers.blazecast.path', '/ws');

        $controller = new TestSignatureController(
            new ApplicationManager(),
            $this->createMock(ChannelManager::class),
            $this->createMock(ChannelConnectionManager::class),
        );

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($requestPath);
        $uri->method('getQuery')->willReturn($queryString . '&auth_signature=' . $clientSignature);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn($method);
        $request->method('getBody')->willReturn('');

        $params = $authParams;
        $params['auth_signature'] = $clientSignature;

        $this->assertTrue($controller->testVerifySignature($request, $params, $appSecret));

        Configure::write('BlazeCast.servers.blazecast.path', '');
    }
}
