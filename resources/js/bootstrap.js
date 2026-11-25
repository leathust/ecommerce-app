import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.Pusher = Pusher;

export function createPaymentEcho({ authEndpoint, csrfToken }) {
    const key = import.meta.env.VITE_PUSHER_APP_KEY;

    if (!key || !authEndpoint) {
        return null;
    }

    const cluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1';
    const scheme = import.meta.env.VITE_PUSHER_SCHEME || 'https';
    const port = Number(import.meta.env.VITE_PUSHER_PORT || (scheme === 'https' ? 443 : 80));
    const host = import.meta.env.VITE_PUSHER_HOST || undefined;

    return new Echo({
        broadcaster: 'pusher',
        key,
        cluster,
        ...(host ? { wsHost: host } : {}),
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint,
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    });
}
