    </main>

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }

    // Double-submit prevention: disable submit buttons after first click
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                if (!submitBtn) return;
                if (submitBtn.dataset.submitting === 'true') {
                    e.preventDefault();
                    return;
                }
                submitBtn.dataset.submitting = 'true';
                submitBtn.disabled = true;
                var originalText = submitBtn.textContent || submitBtn.value;
                if (submitBtn.tagName === 'BUTTON') {
                    submitBtn.textContent = 'Procesando...';
                } else {
                    submitBtn.value = 'Procesando...';
                }
                // Re-enable after 5 seconds in case of error
                setTimeout(function() {
                    submitBtn.disabled = false;
                    submitBtn.dataset.submitting = '';
                    if (submitBtn.tagName === 'BUTTON') {
                        submitBtn.textContent = originalText;
                    } else {
                        submitBtn.value = originalText;
                    }
                }, 5000);
            });
        });
    });
    </script>
</body>
</html>
