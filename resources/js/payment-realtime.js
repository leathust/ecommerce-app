import { createPaymentEcho } from './bootstrap';

const root = document.querySelector('[data-payment-status]');

if (root?.dataset.broadcastChannel && root.dataset.broadcastAuthUrl && root.dataset.paymentPublicId) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const echo = createPaymentEcho({
        authEndpoint: root.dataset.broadcastAuthUrl,
        csrfToken,
    });

    if (echo) {
        const statusUrl = root.dataset.statusUrl;
        const expectedId = root.dataset.paymentPublicId;
        let checking = false;
        let stopped = false;

        const refreshFromServer = async () => {
            if (stopped || checking || !statusUrl) return;

            checking = true;
            try {
                const response = await fetch(statusUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });

                if (!response.ok) return;

                const payload = await response.json();
                if (typeof payload.state === 'string' && payload.state !== root.dataset.state) {
                    stopped = true;
                    window.location.reload();
                }
            } catch {
                // Existing polling in main.js remains the fallback when auth/socket/network fails.
            } finally {
                checking = false;
            }
        };

        echo.private(root.dataset.broadcastChannel)
            .listen('.payment.confirmed', (payload) => {
                if (payload?.payment_public_id !== expectedId || payload?.status !== 'paid') return;
                refreshFromServer();
            });

        const connection = echo.connector?.pusher?.connection;
        connection?.bind('connected', refreshFromServer);
        connection?.bind('state_change', ({ current }) => {
            if (current === 'connected') refreshFromServer();
        });

        window.addEventListener('pagehide', () => {
            stopped = true;
            echo.leave(root.dataset.broadcastChannel);
        }, { once: true });
    }
}
