(() => {
    document.querySelectorAll('[data-curriculum-picker]').forEach(picker => {
        const select = document.getElementById(picker.dataset.select);
        const groups = [...picker.querySelectorAll('[data-topic-course]')];
        const sync = () => {
            groups.forEach(group => {
                const active = group.dataset.topicCourse === select.value;
                group.hidden = !active;
                group.querySelectorAll('input').forEach(input => input.disabled = !active);
                group.querySelectorAll('details').forEach(theme => {
                    const parent = theme.querySelector('[data-theme-check]');
                    const leaves = [...theme.querySelectorAll('[data-leaf]')].filter(x => x !== parent);
                    if (leaves.length) {
                        parent.checked = leaves.every(x => x.checked);
                        parent.indeterminate = leaves.some(x => x.checked) && !parent.checked;
                    }
                });
            });
            const count = picker.querySelectorAll('[data-leaf]:checked:not(:disabled)').length;
            picker.querySelector('[data-selection-label]').textContent = `${count} subtema(s) selecionado(s)`;
            picker.dispatchEvent(new CustomEvent('curriculum-change', {bubbles: true}));
        };
        picker.addEventListener('change', event => {
            if (event.target.matches('[data-theme-check]') && !event.target.matches('[data-leaf]')) {
                event.target.closest('details').querySelectorAll('[data-leaf]').forEach(input => input.checked = event.target.checked);
            }
            sync();
        });
        picker.querySelectorAll('[data-pick]').forEach(button => button.addEventListener('click', () => {
            picker.querySelectorAll('[data-leaf]:not(:disabled)').forEach(input => {
                input.checked = button.dataset.pick === 'all' || (button.dataset.pick === 'taught' && input.dataset.taught === '1');
            });
            sync();
        }));
        select.addEventListener('change', sync);
        sync();
    });
})();
