<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Filter;

use Crustum\BlazeCast\WebSocket\Filter\DefaultMessageFilter;
use Crustum\BlazeCast\WebSocket\Protocol\Message;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DefaultMessageFilter
 */
class DefaultMessageFilterTest extends TestCase
{
    private DefaultMessageFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new DefaultMessageFilter();
    }

    /**
     * @test
     */
    public function filterSupportsEventCriteria(): void
    {
        $message = new Message('test_event', ['data' => 'test'], 'test-channel');

        $this->assertTrue($this->filter->filter($message, ['event' => 'test_event']));
        $this->assertFalse($this->filter->filter($message, ['event' => 'other_event']));
        $this->assertTrue($this->filter->filter($message, ['event' => 'test_*']));
        $this->assertTrue($this->filter->filter($message, ['event' => '*']));
    }

    /**
     * @test
     */
    public function filterSupportsChannelCriteria(): void
    {
        $message = new Message('test_event', ['data' => 'test'], 'public-general');

        $this->assertTrue($this->filter->filter($message, ['channel' => 'public-general']));
        $this->assertFalse($this->filter->filter($message, ['channel' => 'private-user']));
        $this->assertTrue($this->filter->filter($message, ['channel' => 'public-*']));
        $this->assertTrue($this->filter->filter($message, ['channel' => '*']));
    }

    /**
     * @test
     */
    public function filterSupportsDataContainsCriteria(): void
    {
        $message = new Message('test_event', ['user_id' => 123, 'content' => 'hello'], 'test-channel');

        $this->assertTrue($this->filter->filter($message, ['data_contains' => 123]));
        $this->assertTrue($this->filter->filter($message, ['data_contains' => 'hello']));
        $this->assertFalse($this->filter->filter($message, ['data_contains' => 'world']));
    }

    /**
     * @test
     */
    public function filterSupportsUserIdCriteria(): void
    {
        $message = new Message('test_event', ['user_id' => 123], 'test-channel');

        $this->assertTrue($this->filter->filter($message, ['user_id' => 123]));
        $this->assertFalse($this->filter->filter($message, ['user_id' => 456]));
    }

    /**
     * @test
     */
    public function transformAddsTimestamp(): void
    {
        $message = new Message('test_event', ['content' => 'test'], 'test-channel');
        $transformed = $this->filter->transform($message, ['add_timestamp' => true]);

        $data = $transformed->getData();
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('timestamp_iso', $data);
        $this->assertIsInt($data['timestamp']);
        $this->assertIsString($data['timestamp_iso']);
    }

    /**
     * @test
     */
    public function transformAddsUserInfo(): void
    {
        $message = new Message('test_event', ['content' => 'test'], 'test-channel');
        $userInfo = ['id' => 123, 'name' => 'John'];
        $transformed = $this->filter->transform($message, ['add_user_info' => $userInfo]);

        $data = $transformed->getData();
        $this->assertArrayHasKey('user_info', $data);
        $this->assertEquals($userInfo, $data['user_info']);
    }

    /**
     * @test
     */
    public function transformChangesEvent(): void
    {
        $message = new Message('test_event', ['content' => 'test'], 'test-channel');
        $transformed = $this->filter->transform($message, ['change_event' => 'new_event']);

        $this->assertEquals('new_event', $transformed->getEvent());
        $this->assertEquals($message->getData(), $transformed->getData());
        $this->assertEquals($message->getChannel(), $transformed->getChannel());
    }

    /**
     * @test
     */
    public function transformAddsMetadata(): void
    {
        $message = new Message('test_event', ['content' => 'test'], 'test-channel');
        $metadata = ['source' => 'api', 'version' => '1.0'];
        $transformed = $this->filter->transform($message, ['add_metadata' => $metadata]);

        $data = $transformed->getData();
        $this->assertArrayHasKey('metadata', $data);
        $this->assertEquals($metadata, $data['metadata']);
    }

    /**
     * @test
     */
    public function getSupportedCriteriaReturnsExpectedTypes(): void
    {
        $criteria = $this->filter->getSupportedCriteria();

        $this->assertContains('event', $criteria);
        $this->assertContains('channel', $criteria);
        $this->assertContains('data_contains', $criteria);
        $this->assertContains('user_id', $criteria);
        $this->assertContains('connection_id', $criteria);
    }

    /**
     * @test
     */
    public function getSupportedRulesReturnsExpectedTypes(): void
    {
        $rules = $this->filter->getSupportedRules();

        $this->assertContains('add_timestamp', $rules);
        $this->assertContains('add_user_info', $rules);
        $this->assertContains('transform_data', $rules);
        $this->assertContains('change_event', $rules);
        $this->assertContains('add_metadata', $rules);
    }

    /**
     * @test
     */
    public function multipleCriteriaAllMustPass(): void
    {
        $message = new Message('test_event', ['user_id' => 123], 'public-general');

        $criteria = [
            'event' => 'test_*',
            'channel' => 'public-*',
            'user_id' => 123,
        ];

        $this->assertTrue($this->filter->filter($message, $criteria));

        $criteria['user_id'] = 456;
        $this->assertFalse($this->filter->filter($message, $criteria));
    }

    /**
     * @test
     */
    public function multipleTransformationsAreApplied(): void
    {
        $message = new Message('test_event', ['content' => 'test'], 'test-channel');

        $rules = [
            'add_timestamp' => true,
            'add_metadata' => ['source' => 'test'],
            'change_event' => 'transformed_event',
        ];

        $transformed = $this->filter->transform($message, $rules);

        $this->assertEquals('transformed_event', $transformed->getEvent());

        $data = $transformed->getData();
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('metadata', $data);
        $this->assertEquals(['source' => 'test'], $data['metadata']);
    }
}
