window.helper = (() => {
    const alertTimeouts = {};

    function showAlert({
                           message,
                           type = 'info',
                           autoDismiss = 5000,
                           containerSelector = 'body',
                           dismissible = true,
                           additionalClasses = '',
                           id = 'custom-alert',
                       }) {
        if (!message) return;

        const container = document.querySelector(containerSelector);
        if (!container) return;

        // Bootstrap Icon mappings
        const alertTypes = {
            success: { icon: 'bi-check-circle-fill', cls: 'alert-success' },
            danger:  { icon: 'bi-x-circle-fill', cls: 'alert-danger' },
            warning: { icon: 'bi-exclamation-triangle-fill', cls: 'alert-warning' },
            info:    { icon: 'bi-info-circle-fill', cls: 'alert-info' },
        };

        const { icon, cls } = alertTypes[type] || alertTypes.info;

        // Remove existing alert with same ID
        const existingAlert = document.getElementById(id);
        if (existingAlert) {
            clearTimeout(alertTimeouts[id]);
            existingAlert.remove();
        }

        // Create alert
        const alertDiv = document.createElement('div');
        alertDiv.id = id;
        alertDiv.className = `alert ${cls} ${dismissible ? 'alert-dismissible fade show' : ''} ${additionalClasses}`;
        alertDiv.setAttribute('role', 'alert');

        // Icon + message
        alertDiv.innerHTML = `<i class="bi ${icon} me-1"></i>${message}`;

        // Dismiss button (Bootstrap 5)
        if (dismissible) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-close';
            btn.setAttribute('data-bs-dismiss', 'alert');
            btn.setAttribute('aria-label', 'Close');
            alertDiv.appendChild(btn);
        }

        container.prepend(alertDiv);

        // Auto-dismiss
        if (autoDismiss !== null && !isNaN(autoDismiss) && autoDismiss > 0) {
            alertTimeouts[id] = setTimeout(() => {
                alertDiv.classList.remove('show');
                alertDiv.classList.add('hide');
                setTimeout(() => alertDiv.remove(), 200);
            }, autoDismiss);
        }
    }

    const globalLoader = {
        show() {
            const loader = document.getElementById('global-loader');
            if (loader) loader.style.display = 'flex';
        },
        hide() {
            const loader = document.getElementById('global-loader');
            if (loader) loader.style.display = 'none';
        }
    };

    // Input restrictions
    const restrictions = {
        "only-numbers": "0-9",
        "only-letters": "a-zA-Z",
        "only-alphanum": "a-zA-Z0-9",
        "only-decimal": "0-9\\.",
        "only-hex": "0-9a-fA-F",
        "only-username": "a-zA-Z0-9_-",
    };

    function applyRestriction(el, pattern) {
        if (!pattern) return;
        el.value = el.value.replace(new RegExp(`[^${pattern}]`, 'g'), '');
    }

    function getPattern(el) {
        for (let cls in restrictions) {
            if (el.classList.contains(cls)) return restrictions[cls];
        }
        return el.getAttribute("data-allow");
    }

    function initRestrictions() {
        ["input", "paste"].forEach(evt => {
            document.addEventListener(evt, (e) => {
                const el = e.target;
                if (!(el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement)) return;

                const pattern = getPattern(el);
                if (pattern) {
                    evt === "paste"
                        ? setTimeout(() => applyRestriction(el, pattern), 0)
                        : applyRestriction(el, pattern);
                }
            });
        });
    }

    // Initialize restrictions immediately
    initRestrictions();

    return {
        showAlert,
        globalLoader,
        addRestriction: (className, regex) => { restrictions[className] = regex; },
    };
})();
