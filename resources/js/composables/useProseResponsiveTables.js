import { nextTick, onMounted } from 'vue';

/**
 * Wraps tables inside prose content so wide tables scroll horizontally on small screens.
 */
export function wrapProseTables(root) {
    if (!root) {
        return;
    }

    root.querySelectorAll('table').forEach((table) => {
        if (table.parentElement?.classList.contains('prose-table-scroll')) {
            return;
        }

        const wrap = document.createElement('div');
        wrap.className = 'prose-table-scroll';
        wrap.setAttribute('role', 'region');
        wrap.setAttribute('tabindex', '0');
        wrap.setAttribute('aria-label', 'Scrollable table');

        table.before(wrap);
        wrap.appendChild(table);
    });
}

export function useProseResponsiveTables(articleRef) {
    onMounted(() => {
        nextTick(() => wrapProseTables(articleRef.value));
    });
}
