<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelManager;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use PHPUnit\Framework\Attributes\DataProvider;
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
    use VerifiesDoubles;

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
        $timestamp = (string)time();

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
            Double::for(ChannelManager::class),
            Double::for(ChannelConnectionManager::class),
        );

        $apps = ['1' => ['key' => $appKey, 'secret' => $appSecret]];
        $reflection = new ReflectionClass($controller->getApplicationManager());
        $appsProperty = $reflection->getProperty('applications');
        $appsProperty->setValue($controller->getApplicationManager(), $apps);

        $uri = Double::for(UriInterface::class);
        $uri->allows('getPath')->returns($path);
        $uri->allows('getQuery')->returns($clientQueryString . '&auth_signature=' . $clientSignature);

        $request = Double::for(RequestInterface::class);
        $request->allows('getUri')->returns($uri);
        $request->allows('getMethod')->returns($method);
        $request->allows('getBody')->returns('');

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
        $timestamp = (string)time();
        $query = 'auth_key=app-key&auth_timestamp=' . $timestamp . '&auth_version=1.0';
        $appSecret = 'app-secret';

        parse_str($query, $params);
        $authParams = $params;
        ksort($authParams);

        $queryString = http_build_query($authParams);

        $stringToSign = "{$method}\n{$path}\n{$queryString}";

        $clientSignature = hash_hmac('sha256', $stringToSign, $appSecret);
        $params['auth_signature'] = $clientSignature;
        $query .= '&auth_signature=' . $clientSignature;

        $controller = new TestSignatureController(
            new ApplicationManager(),
            Double::for(ChannelManager::class),
            Double::for(ChannelConnectionManager::class),
        );

        $uri = Double::for(UriInterface::class);
        $uri->allows('getPath')->returns($path);
        $uri->allows('getQuery')->returns($query);

        $request = Double::for(RequestInterface::class);
        $request->allows('getUri')->returns($uri);
        $request->allows('getMethod')->returns($method);
        $request->allows('getBody')->returns('');

        $isValid = $controller->testVerifySignature($request, $params, $appSecret);

        $this->assertTrue($isValid, 'Server verifySignature should return true');
    }

    /**
     * Test signatures outside the timestamp tolerance are rejected.
     *
     * @param int $timestamp Timestamp to sign with
     * @return void
     */
    #[DataProvider('timestampToleranceProvider')]
    public function testRejectsSignaturesOutsideTimestampTolerance(int $timestamp): void
    {
        $method = 'GET';
        $path = '/apps/1/channels';
        $appSecret = 'app-secret';
        $authParams = [
            'auth_key' => 'app-key',
            'auth_timestamp' => (string)$timestamp,
            'auth_version' => '1.0',
        ];
        ksort($authParams);
        $queryString = http_build_query($authParams);
        $clientSignature = hash_hmac('sha256', "{$method}\n{$path}\n{$queryString}", $appSecret);
        $params = $authParams + ['auth_signature' => $clientSignature];

        $controller = new TestSignatureController(
            new ApplicationManager(),
            Double::for(ChannelManager::class),
            Double::for(ChannelConnectionManager::class),
        );

        $uri = Double::for(UriInterface::class);
        $uri->allows('getPath')->returns($path);
        $request = Double::for(RequestInterface::class);
        $request->allows('getUri')->returns($uri);
        $request->allows('getMethod')->returns($method);

        $this->assertFalse($controller->testVerifySignature($request, $params, $appSecret));
    }

    /**
     * Provide expired and future timestamps.
     *
     * @return array<string, array<int>>
     */
    public static function timestampToleranceProvider(): array
    {
        return [
            'expired' => [time() - 3600],
            'future' => [time() + 3600],
        ];
    }
}
