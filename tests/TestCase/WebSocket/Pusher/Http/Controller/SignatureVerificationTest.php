<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use ReflectionClass;

/**
 * Signature Verification Test
 *
 * Tests to reproduce and fix the signature verification issue between client and server
 */
class SignatureVerificationTest extends TestCase
{
    /**
     * Test signature generation matches client implementation
     *
     * @return void
     */
    public function testSignatureVerificationMatchesClient(): void
    {
        $method = 'GET';
        $path = '/apps/1/channels';
        $appKey = 'app-key';
        $appSecret = 'app-secret';
        $timestamp = '1750374111';

        $authParams = [
            'auth_key' => $appKey,
            'auth_timestamp' => $timestamp,
            'auth_version' => '1.0',
        ];

        ksort($authParams);
        $clientQueryString = http_build_query($authParams);
        $clientStringToSign = "{$method}\n{$path}\n{$clientQueryString}";
        $clientSignature = hash_hmac('sha256', $clientStringToSign, $appSecret);

        $controller = new TestSignatureController(
            new ApplicationManager(),
            $this->createMock(ChannelManager::class),
            $this->createMock(ChannelConnectionManager::class),
        );

        $apps = ['1' => ['key' => $appKey, 'secret' => $appSecret]];
        $reflection = new ReflectionClass($controller->getApplicationManager());
        $appsProperty = $reflection->getProperty('applications');
        $appsProperty->setValue($controller->getApplicationManager(), $apps);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getQuery')->willReturn($clientQueryString . '&auth_signature=' . $clientSignature);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn($method);
        $request->method('getBody')->willReturn('');

        $paramsWithSignature = $authParams;
        $paramsWithSignature['auth_signature'] = $clientSignature;

        $isValid = $controller->testVerifySignature($request, $paramsWithSignature, $appSecret);

        $this->assertTrue($isValid, 'Server signature verification should match client signature generation');
    }

    /**
     * Test signature verification with actual client parameters from logs
     *
     * @return void
     */
    public function testSignatureVerificationWithLoggedParameters(): void
    {
        $method = 'GET';
        $path = '/apps/1/channels';
        $query = 'auth_key=app-key&auth_timestamp=1750374111&auth_version=1.0&auth_signature=a7f14c178fb34617fac47dc1f2ea2195b0ac9db6b151f1c2e6dc2e83593e823f';
        $appSecret = 'app-secret';
        $clientSignature = 'a7f14c178fb34617fac47dc1f2ea2195b0ac9db6b151f1c2e6dc2e83593e823f';

        parse_str($query, $params);
        $authParams = $params;
        unset($authParams['auth_signature']);
        ksort($authParams);

        $queryString = http_build_query($authParams);

        $stringToSign = "{$method}\n{$path}\n{$queryString}";

        $expectedSignature = hash_hmac('sha256', $stringToSign, $appSecret);

        $controller = new TestSignatureController(
            new ApplicationManager(),
            $this->createMock(ChannelManager::class),
            $this->createMock(ChannelConnectionManager::class),
        );

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getQuery')->willReturn($query);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn($method);
        $request->method('getBody')->willReturn('');

        $isValid = $controller->testVerifySignature($request, $params, $appSecret);

        $this->assertEquals($expectedSignature, $clientSignature, 'Signature should match client implementation');
        $this->assertTrue($isValid, 'Server verifySignature should return true');
    }
}
