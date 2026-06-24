(function ($) {
    const allowedTabs = ['connection', 'display', 'shortcodes'];

    function activeTab() {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab') || 'connection';

        return allowedTabs.includes(tab) ? tab : 'connection';
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

    function syncColorField() {
        const $color = $('#helmful_accent_color');
        const $text = $('#helmful_accent_color_text');
        if ($color.length && $text.length) {
            $text.val($color.val());
        }
    }

    $(function () {
        showPanel(activeTab());

        $('.helmful-admin-tabs .nav-tab').on('click', function (event) {
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
        $('#helmful_accent_color').on('input', syncColorField);
        $('#helmful_accent_color_text').on('input', function () {
            const value = $(this).val();
            if (/^#[0-9a-fA-F]{6}$/.test(value)) {
                $('#helmful_accent_color').val(value);
            }
        });

        $('form').on('submit', function () {
            const value = $('#helmful_accent_color_text').val();
            if (/^#[0-9a-fA-F]{6}$/.test(value)) {
                $('#helmful_accent_color').val(value);
            }
        });

        syncLayoutUi();
        syncColorField();
    });
})(jQuery);
