document.addEventListener('DOMContentLoaded', () => {
    initNavbar();
    initUserMenu();
    initTabs();
    initToasts();
    initModals();
});

function initNavbar() {
    const toggle = document.getElementById('navToggle');
    const menu   = document.getElementById('navMenu');
    if (!toggle || !menu) return;

    toggle.addEventListener('click', () => {
        const open = menu.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open.toString());
        document.body.style.overflow = open ? 'hidden' : '';
    });

    document.addEventListener('click', (e) => {
        if (!toggle.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }
    });
}

function initUserMenu() {
    const btn      = document.getElementById('userMenuBtn');
    const userMenu = btn?.closest('.user-menu');
    if (!btn || !userMenu) return;

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        userMenu.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
        if (!userMenu.contains(e.target)) {
            userMenu.classList.remove('open');
        }
    });
}

function initTabs() {
    document.querySelectorAll('.tabs').forEach(tabContainer => {
        const buttons = tabContainer.querySelectorAll('.tab-btn');
        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.tab;
                buttons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const parent = tabContainer.closest('[data-tabs-parent]') || document;
                parent.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                const targetContent = parent.querySelector(`[data-tab-content="${target}"]`);
                if (targetContent) targetContent.classList.add('active');
            });
        });
    });
}

function initToasts() {
    window.showToast = (message, type = 'info', duration = 4000) => {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            toast.style.transition = '0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    };
}

function initModals() {
    document.querySelectorAll('[data-modal]').forEach(trigger => {
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            const modalId = trigger.dataset.modal;
            const modal   = document.getElementById(modalId);
            if (modal) openModal(modal);
        });
    });

    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay');
            if (modal) closeModal(modal);
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal(overlay);
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay:not(.hidden)').forEach(closeModal);
        }
    });
}

function openModal(modal) {
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

async function apiFetch(url, options = {}) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                   || document.querySelector('input[name="csrf_token"]')?.value
                   || '';

    const defaults = {
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
    };

    const merged = { ...defaults, ...options };
    if (merged.headers && options.headers) {
        merged.headers = { ...defaults.headers, ...options.headers };
    }

    const response = await fetch(url, merged);
    if (!response.ok) {
        const text = await response.text();
        throw new Error(text || `HTTP ${response.status}`);
    }
    return response.json();
}

function confirmAction(message) {
    return confirm(message);
}
