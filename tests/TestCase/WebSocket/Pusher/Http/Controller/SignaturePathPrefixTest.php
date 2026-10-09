<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Cake\Core\Configure;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Signature path-prefix stripping tests (M2)
 */
class SignaturePathPrefixTest extends TestCase
{
    use VerifiesDoubles;

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
            'auth_timestamp' => (string)time(),
            'auth_version' => '1.0',
        ];
        ksort($authParams);
        $queryString = http_build_query($authParams);
        $clientSignature = hash_hmac('sha256', "{$method}\n{$signedPath}\n{$queryString}", $appSecret);

        Configure::write('BlazeCast.servers.blazecast.path', '/ws');

        $controller = new TestSignatureController(
            new ApplicationManager(),
            Double::for(ChannelManager::class),
            Double::for(ChannelConnectionManager::class),
        );

        $uri = Double::for(UriInterface::class);
        $uri->allows('getPath')->returns($requestPath);
        $uri->allows('getQuery')->returns($queryString . '&auth_signature=' . $clientSignature);

        $request = Double::for(RequestInterface::class);
        $request->allows('getUri')->returns($uri);
        $request->allows('getMethod')->returns($method);
        $request->allows('getBody')->returns('');

        $params = $authParams;
        $params['auth_signature'] = $clientSignature;

        $this->assertTrue($controller->testVerifySignature($request, $params, $appSecret));

        Configure::write('BlazeCast.servers.blazecast.path', '');
    }
}
