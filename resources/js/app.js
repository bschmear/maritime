import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

var token = document.head.querySelector('meta[name="csrf-token"]');
// console.log(token)
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
    // window.axios.defaults.headers.common['X-App-Ajax'] = 'true';
} else {
    console.error('CSRF token not found.');
}

const formatPhoneNumber = (value) => {
    if (!value) return '';
    // Remove all non-numeric characters
    const numbers = value.replace(/\D/g, '');
    // Format as (XXX) XXX-XXXX
    if (numbers.length <= 3) {
        return numbers;
    } else if (numbers.length <= 6) {
        return `(${numbers.slice(0, 3)}) ${numbers.slice(3)}`;
    } else {
        return `(${numbers.slice(0, 3)}) ${numbers.slice(3, 6)}-${numbers.slice(6, 10)}`;
    }
};
