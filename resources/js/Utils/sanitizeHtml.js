const ALLOWED_TAGS = new Set([
    'P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'STRIKE',
    'UL', 'OL', 'LI', 'A', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
    'BLOCKQUOTE', 'SPAN', 'DIV', 'HR', 'SUB', 'SUP',
]);

const ALLOWED_ATTRS = {
    A: ['href', 'title', 'target', 'rel'],
    SPAN: ['style'],
    P: ['style'],
    DIV: ['style'],
};

function isSafeHref(href) {
    const value = (href || '').trim();
    if (!value) {
        return false;
    }

    return !/^\s*(javascript|data|vbscript):/i.test(value);
}

function cleanNode(node) {
    [...node.childNodes].forEach((child) => {
        if (child.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        const tag = child.tagName;
        if (!ALLOWED_TAGS.has(tag)) {
            child.replaceWith(...child.childNodes);
            cleanNode(node);

            return;
        }

        [...child.attributes].forEach((attr) => {
            const allowed = ALLOWED_ATTRS[tag] || [];
            const name = attr.name.toLowerCase();

            if (!allowed.map((a) => a.toLowerCase()).includes(name)) {
                child.removeAttribute(attr.name);
            }
        });

        if (tag === 'A') {
            const href = child.getAttribute('href');
            if (!isSafeHref(href)) {
                child.removeAttribute('href');
            } else {
                child.setAttribute('rel', 'noopener noreferrer');
            }
        }

        cleanNode(child);
    });
}

/**
 * Strip scripts and dangerous markup from stored rich text before v-html render.
 */
export function sanitizeHtml(dirty) {
    if (!dirty) {
        return '';
    }

    if (typeof window === 'undefined') {
        return String(dirty);
    }

    const doc = new DOMParser().parseFromString(String(dirty), 'text/html');
    cleanNode(doc.body);

    return doc.body.innerHTML;
}
