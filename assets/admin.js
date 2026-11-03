(function () {
    'use strict';
    function expand(hex) {
        if (/^#[0-9a-f]{3,4}$/i.test(hex)) return '#' + hex.slice(1).split('').map(function (c) { return c + c; }).join('');
        return hex;
    }
    function preview() {
        var box = document.getElementById('hps-preview');
        if (!box) return;
        var colors = {};
        document.querySelectorAll('[data-color-field]').forEach(function (row) {
            var value = row.querySelector('.hps-color-value'), select = row.querySelector('.hps-global');
            var color = value.value;
            if (select && select.value) color = select.selectedOptions[0].dataset.color;
            colors[value.id] = expand(color);
        });
        box.style.backgroundColor = colors.background_color;
        box.style.color = colors.text_color;
        box.style.borderColor = colors.border_color;
        box.style.borderWidth = document.getElementById('border_width').value + 'px';
        box.style.borderRadius = document.getElementById('radius').value + 'px';
        box.style.boxShadow = document.getElementById('shadow_enabled').checked ? '0 ' + document.getElementById('shadow_y').value + 'px ' + document.getElementById('shadow_blur').value + 'px ' + document.getElementById('shadow_spread').value + 'px ' + colors.shadow_color : 'none';
        var button = box.querySelector('button');
        button.style.backgroundColor = colors.button_color; button.style.color = colors.button_text_color;
        button.style.borderRadius = document.getElementById('radius').value + 'px';
        box.querySelector('a').style.color = colors.link_color;
    }
    document.querySelectorAll('[data-color-field]').forEach(function (row) {
        var value = row.querySelector('.hps-color-value'), picker = row.querySelector('.hps-picker');
        var alpha = row.querySelector('.hps-alpha'), output = row.querySelector('output'), global = row.querySelector('.hps-global');
        function refresh() {
            var isGlobal = global && global.value;
            picker.disabled = !!isGlobal; alpha.disabled = !!isGlobal;
            var color = expand(isGlobal ? global.selectedOptions[0].dataset.color : value.value);
            if (/^#[0-9a-f]{6}([0-9a-f]{2})?$/i.test(color)) {
                picker.value = color.slice(0, 7);
                alpha.value = color.length === 9 ? Math.round(parseInt(color.slice(7), 16) / 255 * 100) : 100;
            }
            output.textContent = alpha.value + '%'; preview();
        }
        function change() {
            if (global) global.value = '';
            value.value = picker.value + Math.round(Number(alpha.value) * 255 / 100).toString(16).padStart(2, '0');
            refresh();
        }
        picker.addEventListener('input', change); alpha.addEventListener('input', change);
        value.addEventListener('input', function () { if (global) global.value = value.value.startsWith('elementor:') ? value.value : ''; refresh(); });
        if (global) global.addEventListener('change', function () { value.value = global.value || picker.value + 'ff'; refresh(); });
        refresh();
    });
    document.querySelector('.hps-admin form')?.addEventListener('input', preview);
    document.querySelector('.hps-preview-link')?.addEventListener('click', function (event) { event.preventDefault(); });
    preview();
})();
