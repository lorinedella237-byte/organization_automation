import './bootstrap';
import './theme';
import './sidebar';
import './reveal';
import './role-matrix';

import * as bootstrap from 'bootstrap';
import Alpine from 'alpinejs';

// Bootstrap's JS components (dropdown, modal, collapse, tooltip) are mostly
// driven by data attributes; exposing the namespace lets a page construct one
// imperatively when it needs to.
window.bootstrap = bootstrap;

// Alpine is used sparingly, per the master plan, only where a page needs local
// interactive state that data attributes cannot express.
window.Alpine = Alpine;
Alpine.start();

// Opt-in tooltips.
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
    new bootstrap.Tooltip(element);
});
