/*
 * Keeps the sidebar's scroll position across wire:navigate page swaps. Livewire's navigate
 * replaces the whole <body>, so the scrolled nav element is a brand-new DOM node on every visit
 * and starts back at the top unless we save and reapply the position ourselves.
 */
const STORAGE_KEY = 'propertyflow:sidebar-scroll';

document.addEventListener(
    'scroll',
    (event) => {
        if (event.target instanceof Element && event.target.matches('[data-flux-sidebar]')) {
            sessionStorage.setItem(STORAGE_KEY, String(event.target.scrollTop));
        }
    },
    true,
);

function restoreSidebarScroll() {
    const sidebar = document.querySelector('[data-flux-sidebar]');
    const saved = sessionStorage.getItem(STORAGE_KEY);

    if (sidebar && saved !== null) {
        sidebar.scrollTop = parseInt(saved, 10);
    }
}

document.addEventListener('DOMContentLoaded', restoreSidebarScroll);
document.addEventListener('livewire:navigated', restoreSidebarScroll);
