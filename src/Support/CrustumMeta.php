<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Support;

use JsonException;

/**
 * Reserved Crustum metadata helpers for WebSocket payloads.
 */
final class CrustumMeta
{
    /**
     * Reserved payload key stripped before client delivery.
     *
     * @var string
     */
    public const KEY = '__crustum';

    /**
     * Decode a Pusher `data` field into an array when possible.
     *
     * @param mixed $data Encoded or array payload data.
     * @return array<string, mixed>
     */
    public static function decodePayload(mixed $data): array
    {
        if (is_array($data)) {
            /** @var array<string, mixed> $data */
            return $data;
        }

        if (!is_string($data) || $data === '') {
            return [];
        }

        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['_raw' => $data];
        }

        return is_array($decoded) ? $decoded : ['_raw' => $decoded];
    }

    /**
     * Encode a payload array as a JSON string for the wire.
     *
     * @param array<string, mixed> $payload Payload.
     * @return string
     */
    public static function encodePayload(array $payload): string
    {
        if (array_keys($payload) === ['_raw'] && is_scalar($payload['_raw'])) {
            return (string)$payload['_raw'];
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * Remove reserved metadata from a payload array.
     *
     * @param array<string, mixed> $payload Payload that may contain reserved meta.
     * @return array<string, mixed>
     */
    public static function strip(array $payload): array
    {
        unset($payload[self::KEY]);

        return $payload;
    }

    /**
     * Whether the payload contains reserved metadata.
     *
     * @param array<string, mixed> $payload Payload.
     * @return bool
     */
    public static function hasMeta(array $payload): bool
    {
        return array_key_exists(self::KEY, $payload);
    }
}
