<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Filter;

use Crustum\BlazeCast\WebSocket\Protocol\Message;

/**
 * Interface for message filtering and transformation
 *
 * Provides content-based filtering and message transformation capabilities
 * for PubSub message processing
 *
 * @phpstan-type FilterCriteria array<string, mixed>
 * @phpstan-type TransformRules array<string, mixed>
 */
interface MessageFilterInterface
{
    /**
     * Filter a message based on criteria
     *
     * @param \Crustum\BlazeCast\WebSocket\Protocol\Message $message Message to filter
     * @param FilterCriteria $criteria Filtering criteria
     * @return bool True if message passes filter, false otherwise
     */
    public function filter(Message $message, array $criteria): bool;

    /**
     * Transform a message based on rules
     *
     * @param \Crustum\BlazeCast\WebSocket\Protocol\Message $message Message to transform
     * @param TransformRules $rules Transformation rules
     * @return \Crustum\BlazeCast\WebSocket\Protocol\Message Transformed message
     */
    public function transform(Message $message, array $rules): Message;

    /**
     * Get supported filter criteria types
     *
     * @return array<string> List of supported criteria types
     */
    public function getSupportedCriteria(): array;

    /**
     * Get supported transformation rule types
     *
     * @return array<string> List of supported rule types
     */
    public function getSupportedRules(): array;
}
