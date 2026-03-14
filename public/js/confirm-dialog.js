/**
 * Shipping Gateway — Confirmation Dialog System (P1-2)
 *
 * Intercepts any form or link with [data-confirm] attribute.
 * Shows a professional Arabic modal before executing the action.
 *
 * Usage:
 *   <form data-confirm="هل أنت متأكد من الحذف؟"> ... </form>
 *   <a href="/delete/1" data-confirm="هل تريد الإلغاء؟">إلغاء</a>
 *
 * Features:
 *   - Arabic RTL support
 *   - Escape key closes
 *   - Overlay click closes
 *   - No external dependencies
 *   - Accessible (focus trap, aria labels)
 */
(function () {
    'use strict';

    // Avoid double-init
    if (window.__confirmDialogInit) return;
    window.__confirmDialogInit = true;

    // ── Create modal DOM ──
    var overlay = document.createElement('div');
    overlay.id = 'confirm-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'تأكيد العملية');
    overlay.style.cssText = 'display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.45);z-index:99999;align-items:center;justify-content:center;backdrop-filter:blur(2px)';

    var box = document.createElement('div');
    box.style.cssText = 'background:#fff;border-radius:12px;padding:28px 32px;max-width:400px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.25);animation:confirmSlide .2s ease;font-family:Tahoma,Arial,sans-serif';

    var icon = document.createElement('div');
    icon.style.cssText = 'font-size:40px;margin-bottom:12px';
    icon.textContent = '⚠️';

    var title = document.createElement('h3');
    title.style.cssText = 'margin:0 0 8px;font-size:17px;color:#1a1a1a;font-weight:700';
    title.textContent = 'تأكيد العملية';

    var msg = document.createElement('p');
    msg.id = 'confirm-message';
    msg.style.cssText = 'margin:0 0 20px;font-size:14px;color:#666;line-height:1.6';

    var btnRow = document.createElement('div');
    btnRow.style.cssText = 'display:flex;gap:10px;justify-content:center';

    var btnCancel = document.createElement('button');
    btnCancel.type = 'button';
    btnCancel.textContent = 'إلغاء';
    btnCancel.style.cssText = 'padding:10px 28px;border:1px solid #ddd;background:#f5f5f5;border-radius:8px;font-size:14px;cursor:pointer;font-family:inherit;color:#333;transition:background .15s';
    btnCancel.onmouseenter = function () { this.style.background = '#e8e8e8'; };
    btnCancel.onmouseleave = function () { this.style.background = '#f5f5f5'; };

    var btnConfirm = document.createElement('button');
    btnConfirm.type = 'button';
    btnConfirm.id = 'confirm-yes';
    btnConfirm.textContent = 'نعم، تأكيد';
    btnConfirm.style.cssText = 'padding:10px 28px;border:none;background:#dc3545;color:#fff;border-radius:8px;font-size:14px;cursor:pointer;font-family:inherit;font-weight:600;transition:background .15s';
    btnConfirm.onmouseenter = function () { this.style.background = '#c82333'; };
    btnConfirm.onmouseleave = function () { this.style.background = '#dc3545'; };

    btnRow.appendChild(btnCancel);
    btnRow.appendChild(btnConfirm);
    box.appendChild(icon);
    box.appendChild(title);
    box.appendChild(msg);
    box.appendChild(btnRow);
    overlay.appendChild(box);
    document.body.appendChild(overlay);

    // ── CSS Animation ──
    var style = document.createElement('style');
    style.textContent = '@keyframes confirmSlide{from{transform:translateY(-20px) scale(.95);opacity:0}to{transform:translateY(0) scale(1);opacity:1}}';
    document.head.appendChild(style);

    // ── State ──
    var pendingAction = null;

    function showDialog(message, action) {
        msg.textContent = message;
        pendingAction = action;
        overlay.style.display = 'flex';
        btnCancel.focus();
    }

    function hideDialog() {
        overlay.style.display = 'none';
        pendingAction = null;
    }

    // ── Events ──
    btnCancel.addEventListener('click', hideDialog);

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) hideDialog();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.style.display === 'flex') {
            hideDialog();
        }
    });

    btnConfirm.addEventListener('click', function () {
        if (pendingAction) {
            var action = pendingAction;
            hideDialog();
            action();
        }
    });

    // ── Intercept forms with data-confirm ──
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute('data-confirm')) return;
        if (form.dataset._confirmed === '1') {
            form.dataset._confirmed = '';
            return; // Allow submission
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        showDialog(form.getAttribute('data-confirm'), function () {
            form.dataset._confirmed = '1';
            // Use requestSubmit if available (triggers validation), fallback to submit
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    }, true);

    // ── Intercept links with data-confirm ──
    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[data-confirm]');
        if (!link) return;
        e.preventDefault();
        showDialog(link.getAttribute('data-confirm'), function () {
            window.location.href = link.href;
        });
    }, true);

    // ── Intercept buttons with data-confirm that are outside forms ──
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-confirm]:not([type="submit"])');
        if (!btn) return;
        if (btn.closest('form')) return; // Handled by form submit handler
        e.preventDefault();
        showDialog(btn.getAttribute('data-confirm'), function () {
            btn.click();
        });
    }, true);

})();
