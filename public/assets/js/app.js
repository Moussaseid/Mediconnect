// MediConnect — JS minimal
// Confirmation avant action destructive (attribut data-confirm)
document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-confirm]');
    if (!btn) return;
    if (!confirm(btn.dataset.confirm)) {
        e.preventDefault();
    }
});
