import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const favorites = ref([]);
const loading = ref(false);
let fetchPromise = null;
let pageRef = null;

function getCacheKey() {
    if (typeof window === 'undefined' || !pageRef) {
        return null;
    }
    const userId = pageRef.props.auth?.user?.id;
    if (!userId) {
        return null;
    }

    return `tenant_favorites_${window.location.host}_${userId}`;
}

function getCachedFavorites() {
    const key = getCacheKey();
    if (!key) {
        return null;
    }

    try {
        const raw = localStorage.getItem(key);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed.data)) {
            localStorage.removeItem(key);
            return null;
        }

        return parsed.data;
    } catch {
        localStorage.removeItem(key);
        return null;
    }
}

function cacheFavorites(data) {
    const key = getCacheKey();
    if (!key) {
        return;
    }

    try {
        localStorage.setItem(key, JSON.stringify({ data }));
    } catch {
        // ignore quota errors
    }
}

function clearFavoritesCache() {
    const key = getCacheKey();
    if (key) {
        localStorage.removeItem(key);
    }
}

async function fetchFavorites({ useCache = true } = {}) {
    if (useCache) {
        const cached = getCachedFavorites();
        if (cached) {
            favorites.value = cached;
        }
    }

    if (fetchPromise) {
        return fetchPromise;
    }

    loading.value = true;
    fetchPromise = axios
        .get(route('favorites.index'))
        .then((response) => {
            favorites.value = response.data.data ?? [];
            cacheFavorites(favorites.value);
        })
        .catch((error) => {
            console.error('Failed to fetch favorites:', error);
            if (!favorites.value.length) {
                favorites.value = [];
            }
        })
        .finally(() => {
            loading.value = false;
            fetchPromise = null;
        });

    return fetchPromise;
}

async function addFavorite(payload) {
    clearFavoritesCache();
    const response = await axios.post(route('favorites.store'), payload);
    await fetchFavorites({ useCache: false });
    return response.data.data;
}

async function removeFavorite(id) {
    clearFavoritesCache();
    await axios.delete(route('favorites.destroy', id));
    favorites.value = favorites.value.filter((f) => f.id !== id);
    cacheFavorites(favorites.value);
}

function navigateToFavorite(favorite) {
    const params = favorite.route_params ?? {};
    router.visit(route(favorite.route, params));
}

function humanizeRouteName(name) {
    if (!name) {
        return '';
    }

    return name
        .replace(/\./g, ' ')
        .replace(/[-_]/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

function findNavLabel(items, routeName) {
    if (!items?.length || !routeName) {
        return null;
    }

    for (const item of items) {
        if (item.href && (route().current(item.href) || route().current(item.href + '.*'))) {
            return item.name;
        }
        if (item.children) {
            const childLabel = findNavLabel(item.children, routeName);
            if (childLabel) {
                return childLabel;
            }
        }
    }

    return null;
}

function suggestFavoriteTitle(navItems = []) {
    const page = pageRef ?? usePage();
    const tenantRoute = page.props.tenant_route;
    const appName = page.props.app?.name ?? 'Helmful';

    let title = '';
    if (typeof document !== 'undefined' && document.title) {
        title = document.title;
        const suffix = ` - ${appName}`;
        if (title.endsWith(suffix)) {
            title = title.slice(0, -suffix.length).trim();
        }
        const tenantSuffix = ' - Tenant';
        if (title.endsWith(tenantSuffix)) {
            title = title.slice(0, -tenantSuffix.length).trim();
        }
    }

    if (title) {
        return title;
    }

    const navLabel = findNavLabel(navItems, tenantRoute?.name);
    if (navLabel) {
        return navLabel;
    }

    return humanizeRouteName(tenantRoute?.name);
}

function currentPageUrl() {
    if (typeof window !== 'undefined') {
        return window.location.href;
    }

    const page = pageRef ?? usePage();
    const tenantRoute = page.props.tenant_route;
    if (tenantRoute?.name) {
        try {
            return route(tenantRoute.name, tenantRoute.params ?? {});
        } catch {
            return page.url;
        }
    }

    return page.url;
}

function canAddCurrentPage() {
    const page = pageRef ?? usePage();
    return Boolean(page.props.tenant_route?.name);
}

function isCurrentPageFavorited() {
    const page = pageRef ?? usePage();
    const tenantRoute = page.props.tenant_route;
    if (!tenantRoute?.name) {
        return false;
    }

    const params = tenantRoute.params ?? {};
    return favorites.value.some((f) => {
        if (f.route !== tenantRoute.name) {
            return false;
        }
        const favParams = f.route_params ?? {};
        return JSON.stringify(favParams) === JSON.stringify(params);
    });
}

export function useTenantFavorites() {
    pageRef = usePage();

    return {
        favorites,
        loading,
        fetchFavorites,
        addFavorite,
        removeFavorite,
        navigateToFavorite,
        clearFavoritesCache,
        suggestFavoriteTitle,
        currentPageUrl,
        canAddCurrentPage,
        isCurrentPageFavorited,
    };
}
