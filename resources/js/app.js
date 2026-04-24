import '../css/app.css';
import './bootstrap';
import { registerSW } from 'virtual:pwa-register';
import { formatPhoneNumber } from './Utils/formatPhoneNumber';

// Clean up any previously-registered service workers that don't match the current
// build (e.g. an old vite-plugin-pwa dev SW at /build/dev-sw.js). A stale SW will
// keep requesting its own script URL to check for updates, producing 404s.
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then((registrations) => {
        registrations.forEach((reg) => {
            const url = reg.active?.scriptURL || reg.installing?.scriptURL || reg.waiting?.scriptURL || '';
            if (import.meta.env.DEV || url.includes('dev-sw.js')) {
                reg.unregister();
            }
        });
    });
}
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

registerSW({ immediate: true });

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({
            render: () => h(App, props),
            data() {
                return {
                    toasts: [],
                    loadingOverlay: { visible: false, message: '' },
                };
            },
            methods: {
                createToast(type, message) {
                    const toast = {
                        type: type,
                        message: message,
                    };
                    this.toasts.push(toast);

                    setTimeout(() => {
                        this.dismissToast(this.toasts.indexOf(toast));
                    }, 5000);
                },
                dismissToast(index) {
                    this.toasts.splice(index, 1);
                },
                showLoading(message = 'Loading...') {
                    this.loadingOverlay = { visible: true, message };
                },
                hideLoading() {
                    this.loadingOverlay.visible = false;
                },
                copyLink(elementId, successMessage = 'Copied to clipboard!') {
                    const element = document.getElementById(elementId);

                    if (!element) {
                        console.error(`Element with id "${elementId}" not found`);
                        this.createToast('error', 'Unable to copy - element not found');
                        return;
                    }

                    // Select the text
                    element.select();
                    element.setSelectionRange(0, 99999); // For mobile devices

                    // Try modern clipboard API first
                    navigator.clipboard.writeText(element.value)
                        .then(() => {
                            this.createToast('success', successMessage);
                        })
                        .catch(() => {
                            // Fallback for older browsers
                            try {
                                document.execCommand('copy');
                                this.createToast('success', successMessage);
                            } catch (err) {
                                console.error('Failed to copy text:', err);
                                this.createToast('error', 'Failed to copy to clipboard');
                            }
                        });
                }
            }
        })
            .use(plugin)
            .use(ZiggyVue);

        // Global helper functions
        app.config.globalProperties.$formatPhoneNumber = formatPhoneNumber;
        app.config.globalProperties.$formatDate = formatDate;
        app.config.globalProperties.$formatDateRelative = formatDateRelative;
        app.config.globalProperties.$formatCurrency = formatCurrency;

        const root = app.mount(el);
        app.config.globalProperties.$toast = (type, message) => {
            root.createToast(type, message);
        };

        return root;
    },
    progress: {
        color: '#4B5563',
    },
});

var token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    console.error('CSRF token not found.');
}

        // copyLink(elementId, successMessage = 'Copied to clipboard!') {
        //     const element = document.getElementById(elementId);

        //     if (!element) {
        //         console.error(`Element with id "${elementId}" not found`);
        //         this.createToast('error', 'Unable to copy - element not found');
        //         return;
        //     }

        //     // Select the text
        //     element.select();
        //     element.setSelectionRange(0, 99999); // For mobile devices

        //     // Try modern clipboard API first
        //     navigator.clipboard.writeText(element.value)
        //         .then(() => {
        //             this.createToast('success', successMessage);
        //         })
        //         .catch(() => {
        //             // Fallback for older browsers
        //             try {
        //                 document.execCommand('copy');
        //                 this.createToast('success', successMessage);
        //             } catch (err) {
        //                 console.error('Failed to copy text:', err);
        //                 this.createToast('error', 'Failed to copy to clipboard');
        //             }
        //         });
        // }

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
};

const formatDateRelative = (dateString) => {
    if (!dateString) return 'N/A';

    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    const diffInMinutes = Math.floor(diffInSeconds / 60);
    const diffInHours = Math.floor(diffInMinutes / 60);
    const diffInDays = Math.floor(diffInHours / 24);

    // Show relative time if within last 7 days
    if (diffInSeconds < 60) {
        return 'Just now';
    } else if (diffInMinutes < 60) {
        return `${diffInMinutes} ${diffInMinutes === 1 ? 'minute' : 'minutes'} ago`;
    } else if (diffInHours < 24) {
        return `${diffInHours} ${diffInHours === 1 ? 'hour' : 'hours'} ago`;
    } else if (diffInDays < 7) {
        return `${diffInDays} ${diffInDays === 1 ? 'day' : 'days'} ago`;
    }

    // Otherwise show formatted date
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
};

const formatCurrency = (value) => {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
};
