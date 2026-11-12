<script>
document.addEventListener('click', function (event) {
    const add = event.target.closest('.add-row');
    if (add) {
        const group = add.dataset.group;
        const container = document.querySelector(`[data-rows="${group}"]`);
        const index = Number(container.dataset.nextIndex || container.children.length);
        container.dataset.nextIndex = index + 1;
        container.insertAdjacentHTML('beforeend', document.getElementById(`${group}-template`).innerHTML.replaceAll('__INDEX__', index));
        container.lastElementChild.querySelector('input[type="text"]')?.focus();
        const empty = document.querySelector(`[data-empty-group="${group}"]`);
        if (empty) empty.hidden = true;
        const feedback = document.querySelector('[data-people-feedback]');
        if (feedback) feedback.textContent = @json(__('New person row added.'));
        return;
    }
    const remove = event.target.closest('.remove-row');
    if (remove) {
        const row = remove.closest('.party-row');
        const container = row.parentElement;
        const group = container.dataset.rows;
        if (container.querySelectorAll('.party-row').length > 1 || @json($editing ?? false)) {
            row.remove();
        } else {
            row.querySelectorAll('input').forEach(input => { input.value = ''; });
        }
        const empty = document.querySelector(`[data-empty-group="${group}"]`);
        if (empty) empty.hidden = container.children.length !== 0;
        document.querySelector(`.add-row[data-group="${group}"]`)?.focus();
        const feedback = document.querySelector('[data-people-feedback]');
        if (feedback) feedback.textContent = @json(__('Unsaved person row removed.'));
    }
});
</script>
