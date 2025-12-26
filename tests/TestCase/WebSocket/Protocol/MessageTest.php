<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Protocol;

use Crustum\BlazeCast\WebSocket\Protocol\Message;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Message class
 */
class MessageTest extends TestCase
{
    /**
     * Test that Message can be created from JSON
     *
     * @return void
     */
    public function testFromJsonCreatesMessage(): void
    {
        $json = json_encode([
            'event' => 'test-event',
            'data' => ['key' => 'value'],
            'channel' => 'test-channel',
        ]);

        $message = Message::fromJson($json);

        $this->assertEquals('test-event', $message->getEvent());
        $this->assertEquals(['key' => 'value'], $message->getData());
        $this->assertEquals('test-channel', $message->getChannel());
    }

    /**
     * Test that Message decodes nested JSON string in data field
     *
     * @return void
     */
    public function testFromJsonDecodesNestedJsonString(): void
    {
        $nestedData = ['nested' => 'value'];
        $json = json_encode([
            'event' => 'test-event',
            'data' => json_encode($nestedData),
            'channel' => 'test-channel',
        ]);

        $message = Message::fromJson($json);

        $this->assertEquals('test-event', $message->getEvent());
        $this->assertEquals($nestedData, $message->getData());
        $this->assertEquals('test-channel', $message->getChannel());
    }

    /**
     * Test that Message handles non-JSON string in data field
     *
     * @return void
     */
    public function testFromJsonHandlesNonJsonString(): void
    {
        $json = json_encode([
            'event' => 'test-event',
            'data' => 'plain string value',
            'channel' => 'test-channel',
        ]);

        $message = Message::fromJson($json);

        $this->assertEquals('test-event', $message->getEvent());
        $this->assertEquals('plain string value', $message->getData());
        $this->assertEquals('test-channel', $message->getChannel());
    }

    /**
     * Test that Message handles missing data field
     *
     * @return void
     */
    public function testFromJsonHandlesMissingData(): void
    {
        $json = json_encode([
            'event' => 'test-event',
            'channel' => 'test-channel',
        ]);

        $message = Message::fromJson($json);

        $this->assertEquals('test-event', $message->getEvent());
        $this->assertNull($message->getData());
        $this->assertEquals('test-channel', $message->getChannel());
    }

    /**
     * Test that Message throws exception for invalid JSON
     *
     * @return void
     */
    public function testFromJsonThrowsExceptionForInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        Message::fromJson('{invalid json}');
    }

    /**
     * Test that Message throws exception for missing event
     *
     * @return void
     */
    public function testFromJsonThrowsExceptionForMissingEvent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message must have an event property');

        Message::fromJson(json_encode(['data' => 'test']));
    }

    /**
     * Test that Message handles complex nested JSON
     *
     * @return void
     */
    public function testFromJsonHandlesComplexNestedJson(): void
    {
        $complexData = [
            'user' => [
                'id' => 123,
                'name' => 'Test User',
                'metadata' => ['key' => 'value'],
            ],
        ];
        $json = json_encode([
            'event' => 'client-test',
            'data' => json_encode($complexData),
            'channel' => 'private-test',
        ]);

        $message = Message::fromJson($json);

        $this->assertEquals('client-test', $message->getEvent());
        $this->assertEquals($complexData, $message->getData());
        $this->assertEquals('private-test', $message->getChannel());
    }
}
