import { Trend } from 'k6/metrics';
import ws from 'k6/ws';

/**
 * BlazeCast Benchmark Script
 *
 * You need to run 3 terminals for this.
 *
 * 1. Run the BlazeCast server:
 *
 * bin/cake blazecast server --host 0.0.0.0 --port 8080
 *
 * 2. Run the PHP senders based on the amount of messages per second you want to receive.
 *    The sending rate influences the final benchmark.
 *
 * Low, 1 message per second:
 * php send.php --interval 1 --port 8080 --host 127.0.0.1 --app-key app-key --app-secret app-secret --app-id app-id
 *
 * Mild, 2 messages per second:
 * php send.php --interval 0.5 --port 8080 --host 127.0.0.1 --app-key app-key --app-secret app-secret --app-id app-id
 *
 * Overkill, 10 messages per second:
 * php send.php --interval 0.1 --port 8080 --host 127.0.0.1 --app-key app-key --app-secret app-secret --app-id app-id
 *
 * 3. Run k6 benchmark:
 *
 * k6 run ci-local.js --env WS_HOST=ws://127.0.0.1:8080/app/app-key
 *
 * Connection limit can be configured:
 * k6 run ci-local.js --env WS_HOST=ws://127.0.0.1:8080/app/app-key,MAX_CONNECTIONS=240
 */

const delayTrend = new Trend('message_delay_ms');

let maxP95 = 100;
let maxAvg = 100;

const maxConnections = parseInt(__ENV.MAX_CONNECTIONS || '50');

if (['mysql', 'postgres', 'dynamodb'].includes(__ENV.APP_MANAGER_DRIVER)) {
    maxP95 += 500;
    maxAvg += 100;
}

if (['redis', 'cluster', 'nats'].includes(__ENV.ADAPTER_DRIVER)) {
    maxP95 += 100;
    maxAvg += 100;
}

if (['redis'].includes(__ENV.CACHE_DRIVER)) {
    maxP95 += 20;
    maxAvg += 20;
}

export const options = {
    thresholds: {
        message_delay_ms: [
            { threshold: `p(95)<${maxP95}`, abortOnFail: false },
            { threshold: `avg<${maxAvg}`, abortOnFail: false },
        ],
    },

    scenarios: {
        soakTraffic: {
            executor: 'ramping-vus',
            startVUs: 0,
            startTime: '0s',
            stages: [
                { duration: '50s', target: maxConnections },
                { duration: '110s', target: maxConnections },
            ],
            gracefulRampDown: '40s',
            env: {
                SLEEP_FOR: '160',
                WS_HOST: __ENV.WS_HOST || 'ws://127.0.0.1:8080/app/app-key',
            },
        },

        highTraffic: {
            executor: 'ramping-vus',
            startVUs: 0,
            startTime: '50s',
            stages: [
                { duration: '50s', target: maxConnections },
                { duration: '30s', target: maxConnections },
                { duration: '10s', target: Math.floor(maxConnections * 0.4) },
                { duration: '10s', target: Math.floor(maxConnections * 0.2) },
                { duration: '10s', target: Math.floor(maxConnections * 0.4) },
            ],
            gracefulRampDown: '20s',
            env: {
                SLEEP_FOR: '110',
                WS_HOST: __ENV.WS_HOST || 'ws://127.0.0.1:8080/app/app-key',
            },
        },
    },
};

export default () => {
    ws.connect(__ENV.WS_HOST, null, (socket) => {
        socket.setTimeout(() => {
            socket.close();
        }, __ENV.SLEEP_FOR * 1000);

        socket.on('open', () => {
            socket.setInterval(() => {
                socket.send(JSON.stringify({
                    event: 'pusher:ping',
                    data: JSON.stringify({}),
                }));
            }, 30000);

            socket.on('message', message => {
                let receivedTime = Date.now();

                message = JSON.parse(message);

                if (message.event === 'pusher:connection_established') {
                    socket.send(JSON.stringify({
                        event: 'pusher:subscribe',
                        data: { channel: 'benchmark' },
                    }));
                }

                if (message.event === 'timed-message') {
                    let data = JSON.parse(message.data);

                    delayTrend.add(receivedTime - data.time);
                }
            });
        });
    });
}
