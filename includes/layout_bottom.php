        </div>
    </main>
</div>
<script>
(() => {
    const body = document.body;
    const toggle = document.querySelector('[data-sidebar-open]');
    const closeSidebar = () => {
        body.classList.remove('sidebar-open');
        toggle?.setAttribute('aria-expanded', 'false');
    };
    toggle?.addEventListener('click', () => {
        body.classList.add('sidebar-open');
        toggle.setAttribute('aria-expanded', 'true');
    });
    document.querySelectorAll('[data-sidebar-close]').forEach(el => el.addEventListener('click', closeSidebar));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
    document.querySelectorAll('[data-confirm]').forEach(el => el.addEventListener('click', e => {
        if (!confirm(el.dataset.confirm)) e.preventDefault();
    }));
    document.querySelectorAll('[data-select-filter]').forEach(input => {
        const select = document.getElementById(input.dataset.selectFilter);
        if (!select) return;
        input.addEventListener('input', () => {
            const term = input.value.trim().toLocaleLowerCase('ar');
            Array.from(select.options).forEach((option, index) => {
                if (index === 0 || option.selected) { option.hidden = false; return; }
                option.hidden = term !== '' && !option.text.toLocaleLowerCase('ar').includes(term);
            });
        });
    });
    document.querySelectorAll('[data-copy-option-to]').forEach(select => {
        select.addEventListener('change', () => {
            const target = document.getElementById(select.dataset.copyOptionTo);
            if (target && !target.value.trim() && select.value) target.value = select.options[select.selectedIndex].text;
        });
    });
    document.querySelectorAll('[data-open-details]').forEach(button => {
        button.addEventListener('click', () => {
            const details = document.getElementById(button.dataset.openDetails);
            if (!details) return;
            details.open = true;
            details.scrollIntoView({ behavior: 'smooth', block: 'center' });
            window.setTimeout(() => details.querySelector('select, input:not([type="hidden"]), button')?.focus(), 220);
        });
    });
    document.querySelectorAll('[data-dialog-open]').forEach(button => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById(button.dataset.dialogOpen);
            if (dialog?.showModal) dialog.showModal();
        });
    });
    document.querySelectorAll('[data-dialog-close]').forEach(button => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });
    document.querySelectorAll('dialog').forEach(dialog => {
        dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    });
    document.querySelectorAll('[data-confirm-phrase]').forEach(input => {
        const submit = input.closest('form')?.querySelector('[data-confirm-submit]');
        const validate = () => { if (submit) submit.disabled = input.value.trim() !== input.dataset.confirmPhrase; };
        input.addEventListener('input', validate);
        validate();
    });
    document.querySelectorAll('[data-copy-target]').forEach(button => {
        button.addEventListener('click', async () => {
            const target = document.getElementById(button.dataset.copyTarget);
            if (!target) return;
            const original = button.textContent;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(target.value);
                } else {
                    target.focus();
                    target.select();
                    if (!document.execCommand('copy')) throw new Error('copy failed');
                }
                button.textContent = 'تم النسخ';
                button.classList.add('copied');
            } catch (error) {
                button.textContent = 'حدّد النص وانسخه';
                target.focus();
                target.select();
            }
            window.setTimeout(() => { button.textContent = original; button.classList.remove('copied'); }, 1800);
        });
    });
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('invalid', event => {
            event.target.closest('.field')?.classList.add('invalid');
        }, true);
        form.addEventListener('input', event => {
            if (event.target.checkValidity()) event.target.closest('.field')?.classList.remove('invalid');
        });
    });
})();
</script>
</body>
</html>
