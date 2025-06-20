            </main>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="/admin/js/admin.js"></script>
    
    <script>
    // Toggle sidebar on mobile
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    </script>
</body>
</html>