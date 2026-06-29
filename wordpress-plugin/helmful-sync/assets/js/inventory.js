(function () {
    const config = window.helmfulInventory || {};
    const strings = config.strings || {};

    const modal = document.getElementById('helmful-inventory-quote-modal');
    if (!modal) {
        return;
    }

    const form = document.getElementById('helmful-inventory-quote-form');
    const itemIdField = document.getElementById('helmful-inventory-quote-item-id');
    const itemLabel = document.getElementById('helmful-inventory-quote-item');
    const feedback = document.getElementById('helmful-inventory-quote-feedback');
    const submitButton = document.getElementById('helmful-inventory-quote-submit');
    const nameField = document.getElementById('helmful-inventory-quote-name');
    const emailField = document.getElementById('helmful-inventory-quote-email');
    const phoneField = document.getElementById('helmful-inventory-quote-phone');
    const messageField = document.getElementById('helmful-inventory-quote-message');

    let lastFocusedElement = null;

    function buildItemLabel(trigger) {
        const parts = [];

        if (trigger.dataset.itemBrand) {
            parts.push(trigger.dataset.itemBrand);
        }

        if (trigger.dataset.itemTitle) {
            parts.push(trigger.dataset.itemTitle);
        }

        const details = [];
        if (trigger.dataset.itemYear) {
            details.push(trigger.dataset.itemYear);
        }
        if (trigger.dataset.itemLength) {
            details.push(trigger.dataset.itemLength + ' ft');
        }
        if (trigger.dataset.itemPrice) {
            details.push(trigger.dataset.itemPrice);
        }

        let label = parts.join(' — ');
        if (details.length) {
            label += (label ? ' · ' : '') + details.join(' · ');
        }

        return label || (strings.itemLabel || 'Inventory item');
    }

    function setFeedback(message, isError) {
        if (!feedback) {
            return;
        }

        feedback.hidden = !message;
        feedback.textContent = message || '';
        feedback.classList.toggle('is-error', Boolean(isError));
        feedback.classList.toggle('is-success', Boolean(message) && !isError);
    }

    function resetForm() {
        if (form) {
            form.reset();
        }

        if (itemIdField) {
            itemIdField.value = '';
        }

        setFeedback('', false);

        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = strings.submit || 'Send Request';
        }
    }

    function openModal(trigger) {
        if (!itemIdField || !itemLabel) {
            return;
        }

        resetForm();
        itemIdField.value = trigger.dataset.itemId || '';
        itemLabel.textContent = buildItemLabel(trigger);

        lastFocusedElement = document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;

        modal.hidden = false;
        document.body.classList.add('helmful-inventory-quote-open');

        if (nameField) {
            nameField.focus();
        }
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('helmful-inventory-quote-open');
        resetForm();

        if (lastFocusedElement) {
            lastFocusedElement.focus();
        }
    }

    document.addEventListener('click', function (event) {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const trigger = target.closest('.helmful-inventory-quote-trigger');
        if (trigger) {
            event.preventDefault();
            openModal(trigger);
            return;
        }

        if (target.closest('[data-helmful-quote-close]')) {
            event.preventDefault();
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!config.ajaxUrl || !config.nonce || !itemIdField || !submitButton) {
                setFeedback('Quote requests are unavailable right now.', true);
                return;
            }

            const name = nameField ? nameField.value.trim() : '';
            const email = emailField ? emailField.value.trim() : '';

            if (!name || !email) {
                setFeedback('Please enter your name and email address.', true);
                return;
            }

            submitButton.disabled = true;
            submitButton.textContent = strings.sending || 'Sending...';
            setFeedback('', false);

            const payload = new FormData();
            payload.append('action', 'helmful_inventory_quote');
            payload.append('nonce', config.nonce);
            payload.append('item_id', itemIdField.value);
            payload.append('name', name);
            payload.append('email', email);
            payload.append('phone', phoneField ? phoneField.value.trim() : '');
            payload.append('message', messageField ? messageField.value.trim() : '');

            fetch(config.ajaxUrl, {
                method: 'POST',
                body: payload,
                credentials: 'same-origin',
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.success) {
                        setFeedback(
                            (data.data && data.data.message)
                                || 'Your quote request has been sent. We will be in touch soon.',
                            false,
                        );
                        if (form) {
                            form.querySelectorAll('input:not([type="hidden"]), textarea').forEach(function (field) {
                                field.value = '';
                            });
                        }
                        window.setTimeout(closeModal, 2200);
                        return;
                    }

                    throw new Error(
                        (data.data && data.data.message)
                            || 'Unable to send your request right now. Please try again later.',
                    );
                })
                .catch(function (error) {
                    setFeedback(error.message || 'Unable to send your request right now. Please try again later.', true);
                    submitButton.disabled = false;
                    submitButton.textContent = strings.submit || 'Send Request';
                });
        });
    }
})();
