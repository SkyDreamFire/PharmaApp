</div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="/pharma-app/assets/js/app.js"></script>
    
    <!-- Nettoyage des préférences de thème -->
    <script>
        // Supprimer les préférences de thème stockées et forcer le thème clair
        if (localStorage.getItem('theme')) {
            localStorage.removeItem('theme');
        }
        // S'assurer que le thème est bien défini sur 'light'
        document.documentElement.setAttribute('data-bs-theme', 'light');
    </script>
</body>
</html>