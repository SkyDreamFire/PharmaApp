</div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="/pharma-app/assets/js/app.js"></script>
    
    <!-- Theme switcher script -->
    <script>
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            setTheme(newTheme);
            setStoredTheme(newTheme);
            updateThemeButton();
        }
        
        function updateThemeButton() {
            const currentTheme = document.documentElement.getAttribute('data-bs-theme');
            const themeIcon = document.getElementById('theme-icon');
            const themeText = document.getElementById('theme-text');
            
            if (currentTheme === 'dark') {
                themeIcon.className = 'bi bi-moon-fill';
                themeText.textContent = 'Mode clair';
            } else {
                themeIcon.className = 'bi bi-sun-fill';
                themeText.textContent = 'Mode sombre';
            }
        }
        
        // Initialize theme button on page load
        document.addEventListener('DOMContentLoaded', updateThemeButton);
    </script>
</body>
</html>