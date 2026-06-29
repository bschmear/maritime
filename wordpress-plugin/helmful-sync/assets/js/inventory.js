(function () {
    const config = window.helmfulInventory || {};
    const strings = config.strings || {};

    function setFeedbackElement(feedback, message, isError) {
        if (!feedback) {
            return;
        }

        feedback.hidden = !message;
        feedback.textContent = message || '';
        feedback.classList.toggle('is-error', Boolean(isError));
        feedback.classList.toggle('is-success', Boolean(message) && !isError);
    }

    function submitQuoteRequest(form, fields, onSuccess) {
        if (!config.ajaxUrl || !config.nonce || !fields.submitButton) {
            setFeedbackElement(fields.feedback, 'Quote requests are unavailable right now.', true);
            return;
        }

        const name = fields.nameField ? fields.nameField.value.trim() : '';
        const email = fields.emailField ? fields.emailField.value.trim() : '';

        if (!name || !email) {
            setFeedbackElement(fields.feedback, 'Please enter your name and email address.', true);
            return;
        }

        fields.submitButton.disabled = true;
        fields.submitButton.textContent = strings.sending || 'Sending...';
        setFeedbackElement(fields.feedback, '', false);

        const payload = new FormData();
        payload.append('action', 'helmful_inventory_quote');
        payload.append('nonce', config.nonce);
        payload.append('item_id', fields.itemIdField ? fields.itemIdField.value : '');
        payload.append('name', name);
        payload.append('email', email);
        payload.append('phone', fields.phoneField ? fields.phoneField.value.trim() : '');
        payload.append('message', fields.messageField ? fields.messageField.value.trim() : '');

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
                    setFeedbackElement(
                        fields.feedback,
                        (data.data && data.data.message)
                            || 'Your quote request has been sent. We will be in touch soon.',
                        false,
                    );

                    if (form) {
                        form.querySelectorAll('input:not([type="hidden"]), textarea').forEach(function (field) {
                            field.value = '';
                        });
                    }

                    if (typeof onSuccess === 'function') {
                        onSuccess();
                    }

                    return;
                }

                throw new Error(
                    (data.data && data.data.message)
                        || 'Unable to send your request right now. Please try again later.',
                );
            })
            .catch(function (error) {
                setFeedbackElement(
                    fields.feedback,
                    error.message || 'Unable to send your request right now. Please try again later.',
                    true,
                );
                fields.submitButton.disabled = false;
                fields.submitButton.textContent = strings.submit || 'Send Request';
            });
    }

    const modal = document.getElementById('helmful-inventory-quote-modal');
    const modalForm = document.getElementById('helmful-inventory-quote-form');
    const modalItemIdField = document.getElementById('helmful-inventory-quote-item-id');
    const modalItemLabel = document.getElementById('helmful-inventory-quote-item');
    const modalFeedback = document.getElementById('helmful-inventory-quote-feedback');
    const modalSubmitButton = document.getElementById('helmful-inventory-quote-submit');
    const modalNameField = document.getElementById('helmful-inventory-quote-name');
    const modalEmailField = document.getElementById('helmful-inventory-quote-email');
    const modalPhoneField = document.getElementById('helmful-inventory-quote-phone');
    const modalMessageField = document.getElementById('helmful-inventory-quote-message');

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

    function resetModalForm() {
        if (modalForm) {
            modalForm.reset();
        }

        if (modalItemIdField) {
            modalItemIdField.value = '';
        }

        setFeedbackElement(modalFeedback, '', false);

        if (modalSubmitButton) {
            modalSubmitButton.disabled = false;
            modalSubmitButton.textContent = strings.submit || 'Send Request';
        }
    }

    function openModal(trigger) {
        if (!modal || !modalItemIdField || !modalItemLabel) {
            return;
        }

        resetModalForm();
        modalItemIdField.value = trigger.dataset.itemId || '';
        modalItemLabel.textContent = buildItemLabel(trigger);

        lastFocusedElement = document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;

        modal.hidden = false;
        document.body.classList.add('helmful-inventory-quote-open');

        if (modalNameField) {
            modalNameField.focus();
        }
    }

    function closeModal() {
        if (!modal) {
            return;
        }

        modal.hidden = true;
        document.body.classList.remove('helmful-inventory-quote-open');
        resetModalForm();

        if (lastFocusedElement) {
            lastFocusedElement.focus();
        }
    }

    if (modal) {
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
    }

    if (modalForm) {
        modalForm.addEventListener('submit', function (event) {
            event.preventDefault();

            submitQuoteRequest(modalForm, {
                itemIdField: modalItemIdField,
                feedback: modalFeedback,
                submitButton: modalSubmitButton,
                nameField: modalNameField,
                emailField: modalEmailField,
                phoneField: modalPhoneField,
                messageField: modalMessageField,
            }, function () {
                window.setTimeout(closeModal, 2200);
            });
        });
    }

    const inlineForm = document.getElementById('helmful-inventory-quote-inline-form');
    if (inlineForm) {
        const inlineItemIdField = inlineForm.querySelector('input[name="item_id"]');
        const inlineFeedback = document.getElementById('helmful-inventory-quote-inline-feedback');
        const inlineSubmitButton = document.getElementById('helmful-inventory-quote-inline-submit');
        const inlineNameField = document.getElementById('helmful-inventory-quote-inline-name');
        const inlineEmailField = document.getElementById('helmful-inventory-quote-inline-email');
        const inlinePhoneField = document.getElementById('helmful-inventory-quote-inline-phone');
        const inlineMessageField = document.getElementById('helmful-inventory-quote-inline-message');

        inlineForm.addEventListener('submit', function (event) {
            event.preventDefault();

            submitQuoteRequest(inlineForm, {
                itemIdField: inlineItemIdField,
                feedback: inlineFeedback,
                submitButton: inlineSubmitButton,
                nameField: inlineNameField,
                emailField: inlineEmailField,
                phoneField: inlinePhoneField,
                messageField: inlineMessageField,
            });
        });
    }
})();
