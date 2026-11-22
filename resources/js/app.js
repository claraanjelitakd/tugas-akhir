import { createApp, h, ref } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import PigoLoader from './components/ui/pigo-loader.vue';

// Global loading state (hanya aktif saat navigasi rute/perpindahan halaman berlangsung)
const isNavigating = ref(false);
let navigationTimer = null;
let navigationSequence = 0;

// Listen to Inertia router events: abaikan background request, form in-place, atau filter dengan preserveState agar PigoLoader tidak menutupi layar
router.on('start', (event) => {
    const visit = event?.detail?.visit;
    if (visit?.showProgress === false) {
        return;
    }
    // Jika preserveState aktif (filter tabel in-place, partial reload, atau mutasi form), jangan munculkan Pigo fullscreen
    if (visit?.preserveState) {
        return;
    }

    window.clearTimeout(navigationTimer);
    const sequence = ++navigationSequence;
    navigationTimer = window.setTimeout(() => {
        if (sequence === navigationSequence) {
            isNavigating.value = true;
        }
    }, 180);
});
const resetNavigation = () => {
    window.clearTimeout(navigationTimer);
    navigationSequence += 1;
    isNavigating.value = false;
};
router.on('finish', resetNavigation);
router.on('cancel', resetNavigation);
router.on('error', resetNavigation);

// Tangani otomatis error chunk modul gagal dimuat akibat hash aset berubah setelah build baru
window.addEventListener('vite:preloadError', (event) => {
    event.preventDefault();
    const lastReload = sessionStorage.getItem('vite_last_preload_reload');
    const now = Date.now();
    if (!lastReload || now - parseInt(lastReload, 10) > 8000) {
        sessionStorage.setItem('vite_last_preload_reload', String(now));
        window.location.reload();
    }
});

createInertiaApp({
    title: (title) => `${title} - Tracer Study`,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const exactPath = `./Pages/${name}.vue`;

        if (pages[exactPath]) {
            return resolvePageComponent(exactPath, pages);
        }

        // Fallback case-insensitive jika ada pemanggilan rute yang berbeda besar/kecil huruf
        const lowerPath = exactPath.toLowerCase();
        const foundKey = Object.keys(pages).find((key) => key.toLowerCase() === lowerPath);
        if (foundKey) {
            return typeof pages[foundKey] === 'function' ? pages[foundKey]() : pages[foundKey];
        }

        throw new Error(`Page not found: ${exactPath}`);
    },
    setup({ el, App, props, plugin }) {
        const AppWrapper = {
            setup() {
                return () => h('div', [
                    h(PigoLoader, { isLoading: isNavigating.value }),
                    h(App, props)
                ]);
            }
        };

        return createApp(AppWrapper)
            .use(plugin)
            .mount(el);
    },
});
