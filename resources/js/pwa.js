/*
 * Installable app: registers the service worker and offers an "Install app" button where the
 * browser supports it (Chrome, Edge, Android). The button is the `installPrompt` Alpine
 * component; it stays hidden until the browser says the app can be installed.
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;
    window.dispatchEvent(new CustomEvent('propertyflow:installable'));
});

window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    window.dispatchEvent(new CustomEvent('propertyflow:installed'));
});

document.addEventListener('alpine:init', () => {
    window.Alpine.data('installPrompt', () => ({
        available: deferredPrompt !== null,
        init() {
            window.addEventListener('propertyflow:installable', () => { this.available = true; });
            window.addEventListener('propertyflow:installed', () => { this.available = false; });
        },
        async install() {
            if (deferredPrompt === null) {
                return;
            }

            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
            this.available = false;
        },
    }));
});
