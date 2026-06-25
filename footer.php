    </div><!-- .content -->
</div><!-- .main -->

<script>
// Live clock
function updateClock() {
    const now = new Date();
    document.getElementById('clock').textContent =
        now.toLocaleDateString('id-ID', {weekday:'short', day:'2-digit', month:'short', year:'numeric'}) +
        ' — ' + now.toLocaleTimeString('id-ID');
}
updateClock();
setInterval(updateClock, 1000);

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
        if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
});

// Modal open/close
document.querySelectorAll('[data-modal-open]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById(btn.dataset.modalOpen).classList.add('open');
    });
});
document.querySelectorAll('.modal-close, [data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.closest('.modal-overlay').classList.remove('open');
    });
});
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});
</script>
</body>
</html>
