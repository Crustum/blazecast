<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Filter;

use Crustum\BlazeCast\WebSocket\Protocol\Message;

/**
 * Default message filter implementation
 *
 * Provides common filtering and transformation capabilities
 *
 * @phpstan-import-type FilterCriteria from \Crustum\BlazeCast\WebSocket\Filter\MessageFilterInterface
 * @phpstan-import-type TransformRules from \Crustum\BlazeCast\WebSocket\Filter\MessageFilterInterface
 */
class DefaultMessageFilter implements MessageFilterInterface
{
    /**
     * Supported filter criteria types
     *
     * @var array<string>
     */
    protected array $supportedCriteria = [
        'event',
        'channel',
        'data_contains',
        'data_equals',
        'user_id',
        'connection_id',
    ];

    /**
     * Supported transformation rule types
     *
     * @var array<string>
     */
    protected array $supportedRules = [
        'add_timestamp',
        'add_user_info',
        'transform_data',
        'change_event',
        'add_metadata',
    ];

    /**
     * Filter a message based on criteria
     *
     * @param \Crustum\BlazeCast\WebSocket\Protocol\Message $message Message to filter
     * @param FilterCriteria $criteria Filtering criteria
     * @return bool True if message passes filter, false otherwise
     */
    public function filter(Message $message, array $criteria): bool
    {
        foreach ($criteria as $type => $value) {
            if (!$this->applyCriteria($message, $type, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Transform a message based on rules
     *
     * @param \Crustum\BlazeCast\WebSocket\Protocol\Message $message Message to transform
     * @param TransformRules $rules Transformation rules
     * @return \Crustum\BlazeCast\WebSocket\Protocol\Message Transformed message
     */
    public function transform(Message $message, array $rules): Message
    {
        $transformedMessage = clone $message;

        foreach ($rules as $rule => $params) {
            $transformedMessage = $this->applyRule($transformedMessage, $rule, $params);
        }

        return $transformedMessage;
    }

    /**
     * Get supported filter criteria types
     *
     * @return array<string> List of supported criteria types
     */
    public function getSupportedCriteria(): array
    {
        return $this->supportedCriteria;
    }

    /**
     * Get supported transformation rule types
     *
     * @return array<string> List of supported rule types
     */
    public function getSupportedRules(): array
    {
        return $this->supportedRules;
    }

    /**
     * Apply a single filter criteria
     *
     * @param \Crustum\BlazeCast\WebSocket\Protocol\Message $message Message to check
     * @param string $type Criteria type
     * @param mixed $value Criteria value
     * @return bool True if criteria passes
     */
    protected function applyCriteria(Message $message, string $type, mixed $value): bool
    {
        switch ($type) {
            case 'event':
                return $this->matchesPattern($message->getEvent(), $value);

            case 'channel':
                return $this->matchesPattern($message->getChannel() ?? '', $value);

            case 'data_contains':
                $data = $message->getData();
                if (is_array($data)) {
                    return $this->arrayContains($data, $value);
                }

                return false;

            case 'data_equals':
                return $message->getData() === $value;

            case 'user_id':
                $data = $message->getData();

                return is_array($data) && isset($data['user_id']) && $data['user_id'] === $value;

            case 'connection_id':
                $data = $message->getData();

                return is_array($data) && isset($data['connection_id']) && $data['connection_id'] === $value;

            default:
                return true;
        }
    }

    /**
     * Apply a transformation rule
     *
     * @param \Crustum\BlazeCast\WebSocket\Protocol\Message $message Message to transform
     * @param string $rule Rule type
     * @param mixed $params Rule parameters
     * @return \Crustum\BlazeCast\WebSocket\Protocol\Message Transformed message
     */
    protected function applyRule(Message $message, string $rule, mixed $params): Message
    {
        $data = $message->getData() ?? [];

        switch ($rule) {
            case 'add_timestamp':
                if (is_array($data)) {
                    $data['timestamp'] = time();
                    $data['timestamp_iso'] = date('c');
                }
                break;

            case 'add_user_info':
                if (is_array($data) && is_array($params)) {
                    $data['user_info'] = $params;
                }
                break;

            case 'transform_data':
                if (is_array($params) && isset($params['callback']) && is_callable($params['callback'])) {
                    $data = $params['callback']($data);
                }
                break;

            case 'change_event':
                if (is_string($params)) {
                    return new Message($params, $data, $message->getChannel());
                }
                break;

            case 'add_metadata':
                if (is_array($data) && is_array($params)) {
                    $data['metadata'] = array_merge($data['metadata'] ?? [], $params);
                }
                break;
        }

        return new Message($message->getEvent(), $data, $message->getChannel());
    }

    /**
     * Check if a string matches a pattern (supports wildcards)
     *
     * @param string $string String to check
     * @param string $pattern Pattern to match (supports * wildcards)
     * @return bool True if matches
     */
    protected function matchesPattern(string $string, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if (strpos($pattern, '*') === false) {
            return $string === $pattern;
        }

        $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/';

        return preg_match($regex, $string) === 1;
    }

    /**
     * Check if array contains a value (deep search)
     *
     * @param array<string, mixed> $array Array to search
     * @param mixed $value Value to find
     * @return bool True if found
     */
    protected function arrayContains(array $array, mixed $value): bool
    {
        foreach ($array as $item) {
            if ($item === $value) {
                return true;
            }
            if (is_array($item) && $this->arrayContains($item, $value)) {
                return true;
            }
        }

        return false;
    }
}
