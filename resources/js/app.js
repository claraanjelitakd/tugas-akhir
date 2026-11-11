import { createApp, h, ref } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import PigoLoader from './components/ui/pigo-loader.vue';

// Global loading state (hanya aktif saat navigasi rute/perpindahan halaman berlangsung)
const isNavigating = ref(false);
let navigationTimer = null;
let navigationSequence = 0;

// Listen to Inertia router events: abaikan background request / autosave formulir agar PigoLoader tidak menutupi layar alumni
router.on('start', (event) => {
    const visit = event?.detail?.visit;
    if (visit?.showProgress === false) {
        return;
    }
    // Jika preserveState aktif pada request form (POST/PUT/PATCH) atau partial props reload, jangan munculkan Pigo fullscreen
    if (visit?.preserveState && (['post', 'put', 'patch', 'delete'].includes(visit?.method?.toLowerCase()) || (visit?.only && visit?.only?.length > 0))) {
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
router.on('finish', () => {
    window.clearTimeout(navigationTimer);
    navigationSequence += 1;
    isNavigating.value = false;
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
