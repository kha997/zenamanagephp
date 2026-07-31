/**
 * Ghi lại vị trí cuộn của sidebar operator mỗi khi người dùng cuộn, để một
 * inline script (đặt ngay sau thẻ </aside> trong operator.blade.php) khôi
 * phục lại NGAY LẬP TỨC ở lần tải trang kế tiếp, trước khi trình duyệt kịp
 * vẽ sidebar ở vị trí đầu — tránh hiện tượng "chớp giật" nếu khôi phục bằng
 * script module (luôn chạy sau khi đã vẽ xong DOM ban đầu).
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'operator-sidebar-scroll-top';

    function init() {
        var sidebar = document.querySelector('.operator-sidebar');
        if (!sidebar) return;

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
