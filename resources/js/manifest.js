const manifestForm = document.querySelector('[data-manifest-form]');
if (manifestForm) {
    const container = manifestForm.querySelector('[data-manifest-persons]');
    const template = manifestForm.querySelector('[data-person-template]');
    const count = manifestForm.querySelector('[data-person-count]');
    const changed = () => {
        manifestForm.querySelector('[data-unsaved-notice]').hidden = false;
    };
    const update = () => {
        const rows = [...container.querySelectorAll('[data-manifest-person]')];
        rows.forEach((row, index) => {
            row.querySelector('[data-person-number]').textContent = index + 1;
            row.querySelectorAll('[data-person-field]').forEach((input) => {
                input.name = `persons[${index}][${input.dataset.personField}]`;
            });
            const up = row.querySelector('[data-person-up]');
            const down = row.querySelector('[data-person-down]');
            if (up) up.disabled = index === 0;
            if (down) down.disabled = index === rows.length - 1;
        });
        const pob = manifestForm.dataset.pob;
        count.textContent = `Manifest: ${rows.length} people · Flight plan: ${pob || 'not supplied'}${pob && Number(pob) !== rows.length ? ' — counts do not match' : ''}`;
    };
    manifestForm.querySelector('[data-person-add]')?.addEventListener('click', () => {
        const row = template.content.cloneNode(true);
        container.append(row);
        update();
        container.lastElementChild.querySelector('input').focus();
        changed();
    });
    container.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        const row = button?.closest('[data-manifest-person]');
        if (!row) return;
        if (button.hasAttribute('data-person-remove')) row.remove();
        if (button.hasAttribute('data-person-up') && row.previousElementSibling) container.insertBefore(row, row.previousElementSibling);
        if (button.hasAttribute('data-person-down') && row.nextElementSibling) container.insertBefore(row.nextElementSibling, row);
        update();
        changed();
    });
    manifestForm.addEventListener('input', changed);
    manifestForm.addEventListener('submit', update);
    update();
}
