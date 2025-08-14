/**
 * Application JavaScript principale
 */

// Configuration globale
const App = {
    baseUrl: '/pharma-app',
    apiUrl: '/pharma-app/api',
    
    // Initialisation de l'application
    init() {
        this.initTooltips();
        this.initPopovers();
        this.initDataTables();
        this.initFormValidation();
        this.initSearchFunctionality();
        this.bindEvents();
        console.log('Application initialisée');
    },
    
    // Initialiser les tooltips Bootstrap
    initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    },
    
    // Initialiser les popovers Bootstrap
    initPopovers() {
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    },
    
    // Initialiser les fonctionnalités des tableaux
    initDataTables() {
        const tables = document.querySelectorAll('.data-table');
        tables.forEach(table => {
            this.makeTableSortable(table);
        });
    },
    
    // Rendre un tableau triable
    makeTableSortable(table) {
        const headers = table.querySelectorAll('th[data-sort]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.innerHTML += ' <i class="bi bi-arrow-down-up ms-1"></i>';
            
            header.addEventListener('click', () => {
                const column = header.dataset.sort;
                const direction = header.dataset.direction === 'asc' ? 'desc' : 'asc';
                this.sortTable(table, column, direction);
                header.dataset.direction = direction;
            });
        });
    },
    
    // Trier un tableau
    sortTable(table, column, direction) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            const aVal = a.querySelector(`td[data-sort="${column}"]`)?.textContent.trim() || '';
            const bVal = b.querySelector(`td[data-sort="${column}"]`)?.textContent.trim() || '';
            
            if (direction === 'asc') {
                return aVal.localeCompare(bVal, 'fr', { numeric: true });
            } else {
                return bVal.localeCompare(aVal, 'fr', { numeric: true });
            }
        });
        
        rows.forEach(row => tbody.appendChild(row));
    },
    
    // Initialiser la validation des formulaires
    initFormValidation() {
        const forms = document.querySelectorAll('.needs-validation');
        forms.forEach(form => {
            form.addEventListener('submit', (event) => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    },
    
    // Initialiser la fonctionnalité de recherche
    initSearchFunctionality() {
        const searchInputs = document.querySelectorAll('.search-input');
        searchInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                this.performSearch(e.target.value, e.target.dataset.target);
            });
        });
    },
    
    // Effectuer une recherche dans un tableau
    performSearch(query, targetSelector) {
        const target = document.querySelector(targetSelector);
        if (!target) return;
        
        const rows = target.querySelectorAll('tbody tr');
        const searchQuery = query.toLowerCase();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchQuery)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    },
    
    // Lier les événements
    bindEvents() {
        // Confirmation de suppression
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('delete-btn')) {
                e.preventDefault();
                this.confirmDelete(e.target);
            }
        });
        
        // Soumission de formulaires AJAX
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('ajax-form')) {
                e.preventDefault();
                this.submitAjaxForm(e.target);
            }
        });
    },
    
    // Confirmer la suppression
    confirmDelete(button) {
        const itemName = button.dataset.name || 'cet élément';
        const message = `Êtes-vous sûr de vouloir supprimer ${itemName} ?`;
        
        if (confirm(message)) {
            window.location.href = button.href;
        }
    },
    
    // Soumettre un formulaire en AJAX
    async submitAjaxForm(form) {
        const formData = new FormData(form);
        const action = form.action || window.location.href;
        
        try {
            this.showLoader();
            
            const response = await fetch(action, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert('success', result.message);
                if (result.redirect) {
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1500);
                }
            } else {
                this.showAlert('danger', result.message);
            }
        } catch (error) {
            this.showAlert('danger', 'Une erreur est survenue');
            console.error('Erreur AJAX:', error);
        } finally {
            this.hideLoader();
        }
    },
    
    // Afficher une alerte
    showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        const container = document.querySelector('.alert-container') || document.querySelector('.container-fluid');
        container.insertAdjacentHTML('afterbegin', alertHtml);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            const alert = container.querySelector('.alert');
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    },
    
    // Afficher le loader
    showLoader() {
        const loader = document.createElement('div');
        loader.className = 'spinner-overlay';
        loader.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
        `;
        document.body.appendChild(loader);
    },
    
    // Masquer le loader
    hideLoader() {
        const loader = document.querySelector('.spinner-overlay');
        if (loader) {
            loader.remove();
        }
    },
    
    // Formater un prix
    formatPrice(price) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR'
        }).format(price);
    },
    
    // Formater une date
    formatDate(date) {
        return new Intl.DateTimeFormat('fr-FR').format(new Date(date));
    },
    
    // Valider un email
    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    // Debounce function pour optimiser les recherches
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// Fonctions utilitaires globales
window.AppUtils = {
    // Calculer l'âge
    calculateAge(birthDate) {
        const today = new Date();
        const birth = new Date(birthDate);
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        
        return age;
    },
    
    // Générer un code couleur selon le stock
    getStockStatusClass(current, minimum) {
        if (current <= 0) return 'text-danger';
        if (current <= minimum) return 'text-warning';
        return 'text-success';
    },
    
    // Copier du texte dans le presse-papiers
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            App.showAlert('success', 'Copié dans le presse-papiers');
        } catch (err) {
            console.error('Erreur lors de la copie:', err);
        }
    }
};

// Initialiser l'application quand le DOM est chargé
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});

// Export pour utilisation dans d'autres scripts
window.App = App;