import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

type EchoInstance = InstanceType<typeof Echo<'reverb'>>;

declare global {
  interface Window {
    Pusher: typeof Pusher;
    Echo: EchoInstance;
  }
}

export function initEcho() {
  if (typeof window === 'undefined') return null;
  
  if (window.Echo) return window.Echo;

  window.Pusher = Pusher;

  window.Echo = new Echo({
    broadcaster: 'reverb',
    key: process.env.NEXT_PUBLIC_REVERB_APP_KEY || 'roundtrip-key',
    wsHost: process.env.NEXT_PUBLIC_REVERB_HOST || 'localhost',
    wsPort: parseInt(process.env.NEXT_PUBLIC_REVERB_PORT || '8080'),
    wssPort: parseInt(process.env.NEXT_PUBLIC_REVERB_PORT || '8080'),
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
  });

  return window.Echo;
}
