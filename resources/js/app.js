import { createApp, h, ref } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import PigoLoader from './components/ui/pigo-loader.vue';

// Global loading state (aktif saat navigasi rute berlangsung)
const isNavigating = ref(false);

// Listen to Inertia router events untuk transisi loading yang responsif tanpa jeda buatan
router.on('start', () => {
    isNavigating.value = true;
});
router.on('finish', () => {
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
