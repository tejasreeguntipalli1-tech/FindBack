/**
 * Global Application JavaScript
 */

function showToast(title, message, type = 'info', link = null) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toastId = 'toast-' + Date.now();
    let borderClass = 'border-primary';
    let iconClass = 'fa-info-circle text-primary';

    if (type === 'success' || type === 'match') {
        borderClass = 'border-success';
        iconClass = 'fa-wand-magic-sparkles text-success';
    } else if (type === 'warning') {
        borderClass = 'border-warning';
        iconClass = 'fa-triangle-exclamation text-warning';
    } else if (type === 'danger') {
        borderClass = 'border-danger';
        iconClass = 'fa-circle-exclamation text-danger';
    }

    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center bg-white border ${borderClass} shadow-lg mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="8000">
            <div class="toast-header bg-light">
                <i class="fa-solid ${iconClass} me-2"></i>
                <strong class="me-auto text-dark small">${title}</strong>
                <small class="text-muted">Just now</small>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body small text-secondary">
                ${message}
                ${link ? `<div class="mt-2"><a href="${link}" class="btn btn-sm btn-primary py-0 px-2" style="font-size: 0.75rem;">Open Report</a></div>` : ''}
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', toastHtml);
    const toastEl = document.getElementById(toastId);
    if (window.bootstrap) {
        const bsToast = new bootstrap.Toast(toastEl);
        bsToast.show();
        toastEl.addEventListener('hidden.bs.toast', () => {
            toastEl.remove();
        });
    }
}

// Auto-add loading indicators on form submissions
document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form[data-loading-indicator]');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                const originalHtml = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...`;
                // Safety reset in case of cancelled navigation
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                }, 8000);
            }
        });
    });
});
