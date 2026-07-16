/**
 * Định dạng số kiểu Việt Nam ngay khi nhập: 1.234.567,89
 * - Gắn vào mọi <input data-money> (dùng type="text" inputmode="decimal")
 * - Dấu chấm ngăn cách hàng nghìn, dấu phẩy là phần thập phân
 * - Khi submit, giá trị được chuyển về dạng máy đọc (1234567.89) cho server
 */
(function () {
    'use strict';

    function formatValue(raw) {
        var negative = raw.trim().startsWith('-');
        var cleaned = raw.replace(/[^0-9,]/g, '');
        var commaIndex = cleaned.indexOf(',');
        var intPart = commaIndex === -1 ? cleaned : cleaned.slice(0, commaIndex);
        var decPart = commaIndex === -1 ? null : cleaned.slice(commaIndex + 1).replace(/,/g, '');

        intPart = intPart.replace(/^0+(?=\d)/, '');
        var grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        var result = (negative ? '-' : '') + grouped;
        if (decPart !== null) {
            result += ',' + decPart;
        }
        return result;
    }

    function toServerValue(display) {
        return display.replace(/\./g, '').replace(',', '.');
    }

    function digitsBeforeCaret(value, caret) {
        return value.slice(0, caret).replace(/[^0-9,]/g, '').length;
    }

    function caretFromDigits(value, digits) {
        if (digits <= 0) return 0;
        var count = 0;
        for (var i = 0; i < value.length; i++) {
            if (/[0-9,]/.test(value[i])) {
                count++;
                if (count === digits) return i + 1;
            }
        }
        return value.length;
    }

    function attach(input) {
        if (input.dataset.moneyBound) return;
        input.dataset.moneyBound = '1';

        // Giá trị khởi tạo từ server (vd old input "1234567.89") → hiển thị VN
        if (input.value !== '') {
            input.value = formatValue(input.value.replace('.', ','));
        }

        input.addEventListener('input', function () {
            var caretDigits = digitsBeforeCaret(input.value, input.selectionStart || 0);
            var formatted = formatValue(input.value);
            input.value = formatted;
            var caret = caretFromDigits(formatted, caretDigits);
            input.setSelectionRange(caret, caret);
        });

        var form = input.form;
        if (form && !form.dataset.moneyBound) {
            form.dataset.moneyBound = '1';
            form.addEventListener('submit', function () {
                form.querySelectorAll('input[data-money]').forEach(function (el) {
                    el.value = toServerValue(el.value);
                });
            });
        }
    }

    function init() {
        document.querySelectorAll('input[data-money]').forEach(attach);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
