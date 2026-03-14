/**
 * Shipping Gateway — App JavaScript
 * Handles modals, toasts, confirmations, and interactive UI
 */
document.addEventListener('DOMContentLoaded', function () {

    // ═══ MODALS ═══
    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = 'modal-' + this.getAttribute('data-modal-open');
            var modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = this.closest('.modal-backdrop');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });

    document.querySelectorAll('.modal-backdrop').forEach(function (bg) {
        bg.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.active').forEach(function (m) {
                m.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
    });

    // ═══ TOASTS — Auto-hide ═══
    document.querySelectorAll('.toast-container').forEach(function (container) {
        setTimeout(function () {
            container.style.transition = 'opacity .5s ease, transform .5s ease';
            container.style.opacity = '0';
            container.style.transform = 'translateX(-50%) translateY(-20px)';
            setTimeout(function () {
                if (container.parentNode) container.parentNode.removeChild(container);
            }, 500);
        }, 4000);
    });

    // ═══ CONFIRM DELETE / CANCEL ═══
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(this.getAttribute('data-confirm') || 'هل أنت متأكد؟')) {
                e.preventDefault();
            }
        });
    });

    // ═══ SELECT ALL ═══
    var selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('input[name="selected[]"]').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
        });
    }

    // ═══ FORM DOUBLE-SUBMIT PROTECTION ═══
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
                btn.disabled = true;
                btn.style.opacity = '0.6';
                setTimeout(function () { btn.disabled = false; btn.style.opacity = '1'; }, 5000);
            });
        });
    });

    // ═══ SIDEBAR ACTIVE SCROLL ═══
    var activeItem = document.querySelector('.sidebar-item.active');
    if (activeItem) activeItem.scrollIntoView({ block: 'nearest', behavior: 'smooth' });

    // ═══ RESPONSIVE SIDEBAR ═══
    var toggleBtn = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('mobile-open');
        });
    }
});
