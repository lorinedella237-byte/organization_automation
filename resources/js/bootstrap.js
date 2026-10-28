// HTTP client setup.
//
// Rewritten from the Laravel Mix CommonJS version to ESM for Vite. Echo and
// Pusher are intentionally omitted: broadcasting is outside Release 1.

import axios from 'axios';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Laravel verifies POST/PUT/DELETE requests against the session CSRF token.
// Blade layouts expose it as <meta name="csrf-token">.
const token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}
