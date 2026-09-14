# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.0]

### Added

- Echo mode for RFC6455 conformance testing: raw byte-exact echo on a configurable path (`echo_mode` / `echo_path` in server config), bypassing Pusher handshake and limits
- `Connection::sendBinary()` for sending binary WebSocket frames (opcode `0x2`)
- `Connection::sendCloseFrame()` to send a close frame with payload before terminating the TCP connection
- Autobahn fuzzingclient spec harness: `tests/Specification/spec-server.php` boots a real BlazeCast server in echo mode, `spec-analyze.php` parses results, `client-spec.json` configures the test suite
- `UsersTerminateController` deduplication: a socket subscribed to multiple channels is terminated and counted only once
- Server now rejects failed handshakes (non-101 status) immediately and closes the TCP connection instead of proceeding to the welcome message
- Ignis development skill (`resources/ignis/skills/blazecast-development/SKILL.twig`) and core guidelines (`resources/ignis/guidelines/core.twig`)

### Changed

- Frame parsing delegated to `Ratchet\RFC6455\MessageBuffer` (one per connection) replacing the custom `decodeWebSocketFrame()` method; handles masking, fragmentation, control frames, and UTF-8 validation per RFC6455
- `Server::handleWebSocketData()` refactored into `createMessageBuffer()`, `handleDataMessage()`, and `handleControlFrame()` for clearer separation of data vs control frame processing
- Control frames (ping/pong/close) handled via `handleControlFrame()` — ping echoed with identical payload, pong resets activity, close echoed and connection terminated
- `ServerFactory` config type extended with `echo_mode` and `echo_path` keys

### Fixed

- Rate-limit test deterministic: `testEnsureWithinConnectionMessageRateLimit` now tolerates wall-clock window resets between invokes

## [0.3.0]

### Fixed

- Config loading: plugin bootstrap now checks for user `config/blazecast.php` first, falls back to plugin defaults, and skips loading when `BlazeCast` key already exists
- Log configuration no longer overwrites existing log handlers on repeated bootstrap calls
- Ping state reset: `pong()` now clears all pending ping counters and flags at once instead of decrementing per-type
- `PingInactiveConnectionsJob` skips connections that already have a pending ping awaiting pong
- `PruneStaleConnectionsJob` now checks actual `last_ping_time` against `activity_timeout` instead of relying solely on `isStale()`, and ignores connections that received a pong after the last ping
- Rhythm metrics ingestion logs errors properly instead of calling `debug()`
- Removed stray `print_r` debug output from route listing exception handler

### Changed

- `config/bootstrap.php` simplified: removed inline default config array (handled by `BlazeCastPlugin::bootstrap()`)
- Connection `$pingState` property initialized without inline defaults (set in constructor)
- Nullable typed properties cleaned up across `Server`, `RateLimiter`, `PusherController`, `ControllerFactory`, `PusherChannelFactory`, `RedisPubSubHandler`, `PusherRouter`, `Response`
- `TestServer` simplified: removed redundant null checks after property initialization guarantees

### Added

- `BlazeCastPluginTest` covering config preservation and default loading behavior
- `PingInactiveConnectionsJobTest` case for skipping connections with pending pongs
- `PruneStaleConnectionsJobTest` cases validating pong-aware pruning logic
- PHPStan integration with `cakedc/cakephp-phpstan` extension

### Dev

- Added `cakedc/cakephp-phpstan` to dev dependencies
- PHPUnit compatibility extended to `^11.1 | ^12.5`

## [0.2.0]

### Added

- `OriginGuard` for WebSocket Origin verification and CORS `Access-Control-Allow-Origin` resolution from per-app `allowed_origins` (supports `*` and host patterns such as `*.example.com`)
- `InvalidOrigin` rejection on WebSocket upgrade when Origin is not allowed
- Enforcement of per-app `max_message_size` on inbound WebSocket frames (oversized payloads rejected and connection closed)
- `accept_client_events_from` application setting (`all` / `members` / `none`) with mapping from legacy `enable_client_messages`
- Client-event membership gate: `members` mode requires channel subscription before `client-*` broadcast
- `ServerPath` helpers to strip `servers.blazecast.path` for HMAC signature verification, HTTP route prefix registration, and WebSocket `/app/{key}` path resolution
- Redis scaling fan-out via `PubSubIncomingMessageHandler` and `EventDispatcher` publish path (replaces `null` message handler)
- Cross-node `toOthers` support by carrying raw `socket_id` on Redis pub/sub payloads even when the connection is not local
- TLS `SocketServer` wiring from `options.tls` (`local_cert` / `local_pk` → `tls://` bind)
- Hybrid connection-level WebSocket rate limiting: local per-connection cap on all inbound text frames (`rate_limiter.connection`) with optional `terminate_on_limit`, alongside existing Soketi frontend/backend/read buckets
- Optional Speculum support: when Speculum is installed, BlazeCast periodically persists Speculum debug entries (and flushes on server stop), similar to existing Rhythm ingestion
- Before/after client-delivery hooks so hosts and Speculum can enrich or record broadcasts without leaking internal metadata to browsers
- Automatic stripping of reserved `__crustum` metadata from payloads before they reach WebSocket clients
- Delivery feedback on fan-out (how many clients received a message, with a small sample of connection ids) for monitoring and Speculum

### Changed

- HTTP event triggering routes through `EventDispatcher` so local and scaled broadcast share the same path
- CORS headers prefer application `allowed_origins` instead of always hardcoding `*`
- Welcome `pusher:connection_established` `activity_timeout` resolves from application config, then server config, then `120` (no longer hardcoded)
- Rate limiting remains Soketi-style for API/client-event quotas; connection flood guard is additive
- Broadcast fan-out now cleans payloads for clients first, then notifies listeners with both the internal and client-facing views
- Rhythm soft-loading uses the same plugin-loaded check as Speculum (no hard dependency)
- Rhythm message recording listens on stable BlazeCast event names for sent/received WebSocket traffic

### Documentation

- Document enforced `allowed_origins`, `max_message_size`, `accept_client_events_from`, server `path` signature/route behavior, TLS options, and Redis `socket_id` fan-out in `docs/index.md`
- Echo examples include `wsPath`; Broadcasting examples include `options.path` when `BLAZECAST_SERVER_PATH` is set

## [0.1.0]

### Added

- Initial BlazeCast WebSocket server for CakePHP 5 with Pusher protocol support (public, private, and presence channels)
- Built-in HTTP API compatible with Pusher REST endpoints (events, channels, connections, auth, health, metrics)
- Multi-application manager with key/secret authentication and per-app channel managers
- Connection limits, restart command, timing-safe signature compare (`hash_equals`)
- Soketi-style rate limiting (frontend / backend / read buckets; local and Redis drivers)
- Rhythm / Prometheus metrics hooks and comprehensive logging scopes
- Redis PubSub provider scaffolding for horizontal scaling
- Configuration via `config/blazecast.php` and Cake console `bin/cake blazecast server`
