<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Security;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\WebSocket\Security\OriginGuard;

/**
 * OriginGuardTest
 */
class OriginGuardTest extends TestCase
{
    /**
     * Test wildcard allows any origin.
     *
     * @return void
     */
    public function testWildcardAllowsAnyOrigin(): void
    {
        $this->assertTrue(OriginGuard::isAllowed(['*'], 'https://evil.example'));
        $this->assertTrue(OriginGuard::isAllowed(['*'], null));
    }

    /**
     * Test exact host match.
     *
     * @return void
     */
    public function testExactHostMatch(): void
    {
        $this->assertTrue(OriginGuard::isAllowed(['example.com'], 'https://example.com'));
        $this->assertFalse(OriginGuard::isAllowed(['example.com'], 'https://other.com'));
    }

    /**
     * Test wildcard subdomain patterns.
     *
     * @return void
     */
    public function testWildcardSubdomainPattern(): void
    {
        $this->assertTrue(OriginGuard::isAllowed(['*.example.com'], 'https://api.example.com'));
        $this->assertFalse(OriginGuard::isAllowed(['*.example.com'], 'https://example.com'));
    }

    /**
     * Test CORS header resolution.
     *
     * @return void
     */
    public function testResolveCorsOrigin(): void
    {
        $this->assertSame('*', OriginGuard::resolveCorsOrigin(['*'], 'https://example.com'));
        $this->assertSame('https://example.com', OriginGuard::resolveCorsOrigin(['example.com'], 'https://example.com'));
        $this->assertNull(OriginGuard::resolveCorsOrigin(['example.com'], 'https://other.com'));
    }
}
