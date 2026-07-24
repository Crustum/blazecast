# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

### Changed

- HTTP event triggering routes through `EventDispatcher` so local and scaled broadcast share the same path
- CORS headers prefer application `allowed_origins` instead of always hardcoding `*`
- Welcome `pusher:connection_established` `activity_timeout` resolves from application config, then server config, then `120` (no longer hardcoded)
- Rate limiting remains Soketi-style for API/client-event quotas; connection flood guard is additive

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
