'use strict';

document.addEventListener('DOMContentLoaded', () => {
    feather.replace({ 'stroke-width': 2 });

    const html      = document.documentElement;
    const sidebar   = document.getElementById('sidebar');
    const mainWrap  = document.querySelector('.main-wrap');
    const collapse  = document.getElementById('collapseBtn');
    const mobileBtn = document.getElementById('mobileToggle');
    const themeBtn  = document.getElementById('themeToggle');

    const savedTheme = localStorage.getItem('skona-theme') || 'dark';
    html.setAttribute('data-theme', savedTheme);

    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('skona-theme', next);
            feather.replace({ 'stroke-width': 2 });
        });
    }

    const collapsed = localStorage.getItem('skona-sidebar') === '1';
    if (collapsed && sidebar && mainWrap) {
        sidebar.classList.add('collapsed');
        mainWrap.classList.add('sidebar-collapsed');
    }

    if (collapse) {
        collapse.addEventListener('click', () => {
            const isCollapsed = sidebar.classList.toggle('collapsed');
            mainWrap.classList.toggle('sidebar-collapsed', isCollapsed);
            localStorage.setItem('skona-sidebar', isCollapsed ? '1' : '0');
            feather.replace({ 'stroke-width': 2 });
        });
    }

    if (mobileBtn) {
        mobileBtn.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
        });

        document.addEventListener('click', (e) => {
            if (sidebar && !sidebar.contains(e.target) && !mobileBtn.contains(e.target)) {
                sidebar.classList.remove('mobile-open');
            }
        });
    }

    document.querySelectorAll('[data-copy]').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.querySelector(btn.dataset.copy);
            const text   = target ? (target.textContent || target.value || '') : btn.dataset.copyText || '';
            if (!text) return;
            navigator.clipboard.writeText(text.trim()).then(() => {
                const orig = btn.innerHTML;
                btn.innerHTML = feather.icons.check.toSvg({ width: 16, height: 16 });
                setTimeout(() => { btn.innerHTML = orig; feather.replace({ 'stroke-width': 2 }); }, 1500);
            });
        });
    });

    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.dataset.modalOpen);
            if (modal) modal.classList.add('open');
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('.modal-backdrop').classList.remove('open');
        });
    });

    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', e => {
            if (e.target === backdrop) backdrop.classList.remove('open');
        });
    });

    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', e => {
            if (!confirm(btn.dataset.confirm || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });

    const refreshInterval = parseInt(document.body.dataset.refresh || '0');
    if (refreshInterval > 0) {
        setTimeout(() => location.reload(), refreshInterval * 1000);
    }
});
