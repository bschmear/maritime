(function ($) {
    const allowedTabs = ['general', 'boat-shows', 'inventory', 'brands'];
    const tabAliases = {
        connection: 'general',
        display: 'boat-shows',
        shortcodes: 'general',
    };

    function activeTab() {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab') || 'general';
        const resolved = tabAliases[tab] || tab;

        return allowedTabs.includes(resolved) ? resolved : 'general';
    }

    function showPanel(tab) {
        $('.helmful-admin-panel').removeClass('is-active');
        $('.helmful-admin-panel[data-panel="' + tab + '"]').addClass('is-active');
        $('.helmful-admin-tabs .nav-tab').removeClass('nav-tab-active');
        $('.helmful-admin-tabs .nav-tab[data-tab="' + tab + '"]').addClass('nav-tab-active');
    }

    function syncLayoutUi() {
        const layout = $('input[name="helmful_sync_settings[display][layout]"]:checked').val() || 'stacked';
        const isGrid = layout === 'grid';

        $('.helmful-field--columns').toggleClass('is-hidden', !isGrid);

        $('.helmful-display-preview__frame').attr('data-preview-layout', layout);
    }

    function syncColorFields() {
        $('.helmful-color-field').each(function () {
            const $field = $(this);
            const $color = $field.find('input[type="color"]');
            const $text = $field.find('.helmful-color-field__text');

            if ($color.length && $text.length) {
                $text.val($color.val());
            }
        });
    }

    function preventCredentialAutofill() {
        $('.helmful-no-autofill').each(function () {
            const $field = $(this);
            $field.prop('readonly', true);
            $field.on('focus', function unlock() {
                $field.prop('readonly', false);
                $field.off('focus', unlock);
            });
        });
    }

    $(function () {
        showPanel(activeTab());
        preventCredentialAutofill();

        $('.helmful-admin-tabs .nav-tab').on('click', function (event) {
            event.preventDefault();

            const tab = $(this).data('tab');
            if (!tab) {
                return;
            }

            showPanel(tab);

            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url.toString());
        });

        $('input[name="helmful_sync_settings[display][layout]"]').on('change', syncLayoutUi);

        $('.helmful-color-field input[type="color"]').on('input', function () {
            $(this).closest('.helmful-color-field').find('.helmful-color-field__text').val($(this).val());
        });

        $('.helmful-color-field__text').on('input', function () {
            const value = $(this).val();
            if (/^#[0-9a-fA-F]{6}$/.test(value)) {
                $(this).closest('.helmful-color-field').find('input[type="color"]').val(value);
            }
        });

        $('form').on('submit', function () {
            $('.helmful-color-field').each(function () {
                const $field = $(this);
                const $color = $field.find('input[type="color"]');
                const $text = $field.find('.helmful-color-field__text');
                const value = $text.val();

                if (/^#[0-9a-fA-F]{6}$/.test(value)) {
                    $color.val(value);
                }
            });
        });

        syncLayoutUi();
        syncColorFields();

        $('.helmful-shortcode-field').on('focus click', function () {
            this.select();
        });
    });
})(jQuery);
