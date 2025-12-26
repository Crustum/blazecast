# BlazeCast Benchmark Guide

This directory contains k6 load testing scripts for benchmarking BlazeCast WebSocket server performance.

## Prerequisites

### 1. Install k6

k6 is a modern load testing tool written in Go.

**Windows (using Chocolatey):**
```powershell
choco install k6
```

**Manual:**
Download from https://k6.io/docs/getting-started/installation/

**macOS:**
```bash
brew install k6
```

**Linux:**
```bash
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D53
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
sudo apt-get install k6
```

### 2. Install PHP Dependencies

The PHP sender script requires Composer dependencies:

```bash
composer install
```

## Running the Benchmark

You need **3 terminals** to run the complete benchmark:

### Terminal 1: Start BlazeCast Server

**Important:** Before starting the server, increase the connection limit in your `config/blazecast.php`:

```php
'applications' => [
    [
        'id' => env('BLAZECAST_APP_ID', 'app-id'),
        'key' => env('BLAZECAST_APP_KEY', 'app-key'),
        'secret' => env('BLAZECAST_APP_SECRET', 'app-secret'),
        'name' => env('BLAZECAST_APP_NAME', 'Default BlazeCast App'),
        'max_connections' => env('BLAZECAST_APP_MAX_CONNECTIONS', 500), // Increased for benchmarking
        // ... rest of config
    ],
],
```

Or set it in your `.env` file:
```ini
BLAZECAST_APP_MAX_CONNECTIONS=500
```

The benchmark creates up to 250 concurrent connections, so set `max_connections` to at least 300-500 to allow headroom.

Then start the server:
```bash
bin/cake blazecast server --host 0.0.0.0 --port 8080
```

### Terminal 2: Run PHP Message Sender

The PHP script sends test messages to the server at a specified interval:

**Low load (1 message per second):**
```bash
cd plugins/BlazeCast/docs/benchmark
php send.php --interval 1 --port 8080 --host 127.0.0.1 --app-key app-key --app-secret app-secret --app-id app-id
```

**Medium load (2 messages per second):**
```bash
php send.php --interval 0.5 --port 8080 --host 127.0.0.1 --app-key app-key --app-secret app-secret --app-id app-id
```

**High load (10 messages per second):**
```bash
php send.php --interval 0.1 --port 8080 --host 127.0.0.1 --app-key app-key --app-secret app-secret --app-id app-id
```

### Terminal 3: Run k6 Benchmark

```bash
cd plugins/BlazeCast/docs/benchmark
k6 run ci-local.js --env WS_HOST=ws://127.0.0.1:8080/app/app-key
```

Or use the default (already configured for port 8080):
```bash
k6 run ci-local.js
```

## Understanding the Results

The benchmark measures:
- **message_delay_ms**: Time between when a message is sent and when it's received by clients
- **p(95)**: 95th percentile latency (should be < 100ms for local, < 200ms with Redis)
- **avg**: Average latency (should be < 100ms for local, < 200ms with Redis)

The script runs two scenarios:
1. **soakTraffic**: Gradually ramps up to 250 concurrent connections and maintains them
2. **highTraffic**: Variable load with connection churn starting after 50 seconds

## Customizing the Benchmark

### Change Port

Edit `ci-local.js` and update the `WS_HOST` default:
```javascript
WS_HOST: __ENV.WS_HOST || 'ws://127.0.0.1:8080/app/app-key',
```

Or use environment variable:
```bash
k6 run ci-local.js --env WS_HOST=ws://127.0.0.1:8080/app/app-key
```

### Change Load Levels

Edit the `scenarios` section in `ci-local.js` to adjust:
- `target`: Number of concurrent virtual users
- `duration`: How long each stage runs
- `stages`: Ramp-up and ramp-down patterns

### Adjust Thresholds

Edit the `thresholds` section to change acceptable latency:
```javascript
thresholds: {
    message_delay_ms: [
        { threshold: 'p(95)<200', abortOnFail: false },
        { threshold: 'avg<100', abortOnFail: false },
    ],
},
```
