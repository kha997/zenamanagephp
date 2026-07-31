/**
 * Giữ nguyên vị trí cuộn của sidebar operator qua các lần điều hướng trang.
 * Mỗi link trong sidebar là một lần tải trang mới (không phải SPA), nên
 * trình duyệt không tự khôi phục scrollTop của phần tử con
 * (position:fixed; overflow-y:auto) như nó làm với scroll của cả trang.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'operator-sidebar-scroll-top';

    function init() {
        var sidebar = document.querySelector('.operator-sidebar');
        if (!sidebar) return;

        var saved = sessionStorage.getItem(STORAGE_KEY);
        if (saved !== null) {
            sidebar.scrollTop = parseInt(saved, 10) || 0;
        }

        sidebar.addEventListener('scroll', function () {
            sessionStorage.setItem(STORAGE_KEY, String(sidebar.scrollTop));
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
