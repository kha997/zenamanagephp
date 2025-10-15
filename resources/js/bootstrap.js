// import _ from 'lodash';
// window._ = _;

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

// import axios from 'axios';
// import Echo from 'laravel-echo';
// import Pusher from 'pusher-js';

// window.axios = axios;
// window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Thiết lập CSRF token
// let token = document.head.querySelector('meta[name="csrf-token"]');
// if (token) {
//     window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
// } else {
//     console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
// }

// Thiết lập JWT token nếu có
// const jwtToken = localStorage.getItem('jwt_token');
// if (jwtToken) {
//     window.axios.defaults.headers.common['Authorization'] = `Bearer ${jwtToken}`;
// }

// Thiết lập Laravel Echo cho WebSocket
// window.Pusher = Pusher;

// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: import.meta.env.VITE_PUSHER_APP_KEY,
//     cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
//     wsHost: import.meta.env.VITE_PUSHER_HOST ?? '127.0.0.1',
//     wsPort: import.meta.env.VITE_PUSHER_PORT ?? 6001,
//     wssPort: import.meta.env.VITE_PUSHER_PORT ?? 6001,
//     forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'http') === 'https',
//     encrypted: (import.meta.env.VITE_PUSHER_SCHEME ?? 'http') === 'https',
//     disableStats: true,
//     enabledTransports: ['ws', 'wss'],
//     auth: {
//         headers: {
//             Authorization: `Bearer ${jwtToken}`,
//         },
//     },
// });

// Xử lý lỗi kết nối
// window.Echo.connector.pusher.connection.bind('error', function(err) {
//     console.error('WebSocket connection error:', err);
// });

// window.Echo.connector.pusher.connection.bind('connected', function() {
//     console.log('WebSocket connected successfully');
// });

// window.Echo.connector.pusher.connection.bind('disconnected', function() {
//     console.log('WebSocket disconnected');
// });
