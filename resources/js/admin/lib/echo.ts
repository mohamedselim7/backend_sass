import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Realtime for the Admin. Authorisation goes through the session-guarded
 * endpoint registered in routes/web.php (/admin/broadcasting/auth), so agents
 * never need a JWT — that stays the customer app's mechanism.
 */
let echo: Echo<'pusher'> | null = null;

export function getEcho(): Echo<'pusher'> | null {
    if (typeof window === 'undefined') return null;
    if (echo) return echo;

    const boot = window.__ADMIN_BOOT__;

    if (!boot?.pusherKey) return null;

    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

    window.Pusher = Pusher;

    echo = new Echo({
        broadcaster: 'pusher',
        key: boot.pusherKey,
        cluster: boot.pusherCluster ?? 'eu',
        forceTLS: true,
        authEndpoint: '/admin/broadcasting/auth',
        auth: { headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } },
    });

    return echo;
}

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}
