// =============================================
// Aurum Bank - Main JS
// =============================================

document.addEventListener('DOMContentLoaded', function () {

    // ----- Theme Toggle (Dark / Light Mode) -----
    const themeToggle = document.getElementById('themeToggle');
    const root = document.documentElement;

    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
        localStorage.setItem('aub_theme', theme);
    }

    // Init on load (already done inline but re-apply for safety)
    var savedTheme = localStorage.getItem('aub_theme') || 'light';
    applyTheme(savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            var current = root.getAttribute('data-theme') || 'light';
            applyTheme(current === 'dark' ? 'light' : 'dark');
        });
    }


    // ----- Mobile Sidebar Toggle -----
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');

    if (mobileBtn && sidebar) {
        mobileBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }

    // ----- Auto-dismiss Alerts -----
    const alerts = document.querySelectorAll('.alert[data-auto-dismiss]');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            alert.style.transition = 'all 0.4s ease';
            setTimeout(() => alert.remove(), 400);
        }, 4000);
    });

    // ----- Card Number Formatting -----
    const cardInput = document.getElementById('card_number_input');
    if (cardInput) {
        cardInput.addEventListener('input', function () {
            let val = this.value.replace(/\D/g, '').substring(0, 16);
            this.value = val.replace(/(.{4})/g, '$1 ').trim();
        });
    }

    // ----- Phone Number Formatting -----
    const phoneInput = document.querySelector('input[name="phone"]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^\d+\s-]/g, '');
        });
    }

    // ----- Amount Input: numbers only -----
    const amountInputs = document.querySelectorAll('input[name="amount"]');
    amountInputs.forEach(input => {
        input.addEventListener('input', function () {
            this.value = this.value.replace(/[^\d.]/g, '');
            const parts = this.value.split('.');
            if (parts.length > 2) this.value = parts[0] + '.' + parts[1];
        });
    });

    // ----- Toggle Password Visibility -----
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function () {
            const input = document.querySelector(this.dataset.target);
            if (!input) return;
            const isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            this.innerHTML = isPass
                ? '<i class="fas fa-eye-slash"></i>'
                : '<i class="fas fa-eye"></i>';
        });
    });

    // ----- Transfer Form: validate -----
    const transferForm = document.getElementById('transferForm');
    if (transferForm) {
        transferForm.addEventListener('submit', function (e) {
            const amount = parseFloat(document.getElementById('amount')?.value || 0);
            const from   = document.getElementById('from_account')?.value;
            const to     = document.getElementById('to_account')?.value;
            if (!from || !to) {
                e.preventDefault();
                showToast('يرجى اختيار الحسابات', 'error');
                return;
            }
            if (from === to) {
                e.preventDefault();
                showToast('لا يمكن التحويل من نفس الحساب إليه', 'error');
                return;
            }
            if (!amount || amount <= 0) {
                e.preventDefault();
                showToast('يرجى إدخال مبلغ صحيح', 'error');
                return;
            }
        });
    }

    // ----- Confirm Dangerous Actions -----
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm)) e.preventDefault();
        });
    });

    // ----- Counter Animation -----
    function animateCounter(el) {
        const target = parseFloat(el.dataset.target || 0);
        const duration = 1200;
        const step = 16;
        const steps = duration / step;
        const increment = target / steps;
        let current = 0;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) { current = target; clearInterval(timer); }
            const formatted = current.toLocaleString('ar-SA', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            el.textContent = formatted;
        }, step);
    }

    document.querySelectorAll('[data-counter]').forEach(el => {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter(el);
                    observer.unobserve(el);
                }
            });
        });
        observer.observe(el);
    });

    // ----- Toast Notification -----
    window.showToast = function (message, type = 'info') {
        const colors = {
            success: '#10b981',
            error:   '#ef4444',
            warning: '#f59e0b',
            info:    '#f43f8a',
        };
        const icons = {
            success: 'fa-check-circle',
            error:   'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info:    'fa-info-circle',
        };
        const toast = document.createElement('div');
        toast.style.cssText = `
            position:fixed; bottom:24px; left:24px; z-index:9999;
            background:#fff1f5; color:#1a0f12;
            padding:14px 18px; border-radius:12px;
            box-shadow:0 10px 40px rgba(0,0,0,0.15);
            display:flex; align-items:center; gap:10px;
            font-family:'Cairo',sans-serif; font-size:14px; font-weight:500;
            border-right:4px solid ${colors[type]};
            transform:translateY(20px); opacity:0;
            transition:all 0.3s ease; max-width:320px;
        `;
        toast.innerHTML = `<i class="fas ${icons[type]}" style="color:${colors[type]};font-size:16px;"></i> ${message}`;
        document.body.appendChild(toast);
        requestAnimationFrame(() => {
            toast.style.transform = 'translateY(0)';
            toast.style.opacity   = '1';
        });
        setTimeout(() => {
            toast.style.transform = 'translateY(20px)';
            toast.style.opacity   = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    };

    // ----- Sidebar Overlay Styles -----
    if (sidebar) {
        const ov = document.createElement('div');
        ov.id = 'sidebarOverlay';
        ov.style.cssText = `
            display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5);
            z-index:99; transition:opacity 0.2s ease;
        `;
        ov.addEventListener('click', () => {
            sidebar.classList.remove('open');
            ov.style.display = 'none';
        });
        document.body.appendChild(ov);

        if (mobileBtn) {
            mobileBtn.addEventListener('click', () => {
                ov.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
            });
        }
    }
});
