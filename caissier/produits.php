<?php
$pageTitle = 'Consultation des Médicaments';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

$db = Database::getInstance();

// Récupérer les médicaments
$medicaments = $db->select("
    SELECT m.*, c.nom as categorie_nom, f.nom as fournisseur_nom,
           (m.ancien_stock + m.nouveau_stock) as stock_total
    FROM medicaments m
    LEFT JOIN categories c ON m.categorie_id = c.id
    LEFT JOIN fournisseurs f ON m.fournisseur_id = f.id
    WHERE m.actif = 1
    ORDER BY m.nom ASC
");

$categories = $db->select("SELECT * FROM categories ORDER BY nom ASC");
?>

<div class="page-header">
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="bi bi-capsule me-2"></i>
            Consultation des Médicaments
        </h1>
        <p class="page-subtitle">Consultez le catalogue des médicaments disponibles</p>
    </div>
</div>

<!-- Filtres et recherche -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-5">
                <div class="search-box">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="form-control search-input" placeholder="Rechercher un médicament..." data-target="#medicamentsTable">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="categorieFilter">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $categorie): ?>
                        <option value="<?= $categorie['id'] ?>"><?= escape($categorie['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="stockFilter">
                    <option value="">Tous les stocks</option>
                    <option value="disponible">Disponible</option>
                    <option value="faible">Stock faible</option>
                    <option value="vide">Stock vide</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    Reset
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-primary text-white">
                    <i class="bi bi-capsule"></i>
                </div>
                <h3 class="stats-number text-primary"><?= count($medicaments) ?></h3>
                <p class="stats-label">Médicaments</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-success text-white">
                    <i class="bi bi-check-circle"></i>
                </div>
                <h3 class="stats-number text-success"><?= count(array_filter($medicaments, fn($m) => $m['stock_actuel'] > $m['stock_minimum'])) ?></h3>
                <h3 class="stats-number text-success"><?= count(array_filter($medicaments, fn($m) => getStockTotal($m) > $m['stock_minimum'])) ?></h3>
                <p class="stats-label">Stock OK</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-warning text-white">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <h3 class="stats-number text-warning"><?= count(array_filter($medicaments, fn($m) => $m['stock_actuel'] <= $m['stock_minimum'] && $m['stock_actuel'] > 0)) ?></h3>
                <h3 class="stats-number text-warning"><?= count(array_filter($medicaments, fn($m) => getStockTotal($m) <= $m['stock_minimum'] && getStockTotal($m) > 0)) ?></h3>
                <p class="stats-label">Stock faible</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-danger text-white">
                    <i class="bi bi-x-circle"></i>
                </div>
                <h3 class="stats-number text-danger"><?= count(array_filter($medicaments, fn($m) => $m['stock_actuel'] == 0)) ?></h3>
                <h3 class="stats-number text-danger"><?= count(array_filter($medicaments, fn($m) => getStockTotal($m) == 0)) ?></h3>
                <p class="stats-label">Stock vide</p>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des médicaments -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-list me-2"></i>
            Catalogue des Médicaments (<?= count($medicaments) ?>)
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table" id="medicamentsTable">
                <thead>
                    <tr>
                        <th data-sort="nom">Médicament</th>
                        <th data-sort="categorie">Catégorie</th>
                        <th data-sort="prix_vente">Prix</th>
                        <th data-sort="stock_actuel">Stock</th>
                        <th data-sort="fournisseur">Fournisseur</th>
                        <th data-sort="date_expiration">Expiration</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medicaments as $medicament): ?>
                        <?php 
                        $stock_total = getStockTotal($medicament);
                        $date_active = getDateExpirationActive($medicament);
                        ?>
                        <tr data-categorie="<?= $medicament['categorie_id'] ?>" 
                            data-stock-status="<?= $stock_total <= 0 ? 'vide' : ($stock_total <= $medicament['stock_minimum'] ? 'faible' : 'disponible') ?>">
                            <td data-sort="nom">
                                <div>
                                    <strong><?= escape($medicament['nom']) ?></strong>
                                    <?php if ($medicament['code_barre']): ?>
                                        <br><small class="text-muted">
                                            <i class="bi bi-upc-scan me-1"></i><?= escape($medicament['code_barre']) ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if ($medicament['description']): ?>
                                        <br><small class="text-muted"><?= escape(substr($medicament['description'], 0, 50)) ?>...</small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-sort="categorie">
                                <?= $medicament['categorie_nom'] ? '<span class="badge bg-secondary">' . escape($medicament['categorie_nom']) . '</span>' : '-' ?>
                            </td>
                            <td data-sort="prix_vente">
                                <div>
                                    <strong class="text-success"><?= formatPrice($medicament['prix_vente']) ?></strong>
                                    <br><small class="text-muted">Achat: <?= formatPrice($medicament['prix_achat']) ?></small>
                                </div>
                            </td>
                            <td data-sort="stock_actuel">
                                <div>
                                    <span class="badge <?= $stock_total <= 0 ? 'bg-danger' : ($stock_total <= $medicament['stock_minimum'] ? 'bg-warning' : 'bg-success') ?>">
                                        <?= $stock_total ?> <?= escape($medicament['unite']) ?>
                                    </span>
                                    <br><small class="text-muted">Min: <?= $medicament['stock_minimum'] ?></small>
                                    <?php if ($date_active): ?>
                                        <br><small class="text-info">Expire: <?= formatDate($date_active) ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-sort="fournisseur">
                                <?= $medicament['fournisseur_nom'] ? escape($medicament['fournisseur_nom']) : '-' ?>
                            </td>
                            <td data-sort="date_expiration">
                                <?php if ($date_active): ?>
                                    <?= formatDate($date_active) ?>
                                    <?php
                                    $today = new DateTime();
                                    $expiration = new DateTime($date_active);
                                    $diff = $today->diff($expiration);
                                    if ($expiration < $today): ?>
                                        <br><small class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Expiré</small>
                                    <?php elseif ($diff->days <= 30): ?>
                                        <br><small class="text-warning"><i class="bi bi-clock me-1"></i>Expire bientôt</small>
                                    <?php endif; ?>
                                    
                                    <!-- Détails FEFO -->
                                    <?php if ($medicament['ancien_stock'] > 0 && $medicament['nouveau_stock'] > 0): ?>
                                        <br><small class="text-muted">
                                            <i class="bi bi-layers me-1"></i>
                                            A: <?= $medicament['ancien_stock'] ?> | N: <?= $medicament['nouveau_stock'] ?>
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="voirDetails(<?= htmlspecialchars(json_encode($medicament)) ?>)"
                                            data-bs-toggle="tooltip" 
                                            title="Voir les détails">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if ($stock_total > 0): ?>
                                        <button class="btn btn-sm btn-outline-success" 
                                                onclick="ajouterAuPanier(<?= $medicament['id'] ?>, '<?= escape($medicament['nom']) ?>', <?= $medicament['prix_vente'] ?>)"
                                                data-bs-toggle="tooltip" 
                                                title="Ajouter au panier">
                                            <i class="bi bi-cart-plus"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled
                                                data-bs-toggle="tooltip" 
                                                title="Stock épuisé">
                                            <i class="bi bi-cart-x"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal détails médicament -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Détails du médicament</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Contenu chargé dynamiquement -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" id="ajouterPanierBtn" onclick="ajouterAuPanierFromModal()">
                    <i class="bi bi-cart-plus me-2"></i>Ajouter au panier
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Panier flottant -->
<div class="position-fixed bottom-0 end-0 p-3 d-none d-lg-block" style="z-index: 1050;">
    <div class="card shadow" id="panierFlottant" style="display: none; width: 280px;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                <i class="bi bi-cart me-2"></i>Panier (<span id="panierCount">0</span>)
            </h6>
            <button class="btn btn-sm btn-outline-secondary" onclick="viderPanier()">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="card-body p-2" id="panierContent" style="max-height: 180px; overflow-y: auto; font-size: 0.875rem;">
            <!-- Articles du panier -->
        </div>
        <div class="card-footer">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small><strong>Total: <span id="panierTotal">0,00 FCFA</span></strong></small>
            </div>
            <a href="/pharma-app/caissier/facture.php" class="btn btn-success btn-sm w-100">
                <i class="bi bi-receipt me-2"></i>Créer facture
            </a>
        </div>
    </div>
</div>

<!-- Panier mobile (affiché uniquement sur mobile) -->
<div class="d-lg-none">
    <div class="position-fixed bottom-0 start-0 end-0 bg-white border-top shadow-lg p-3" id="panierMobile" style="display: none; z-index: 1050;">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">
                <i class="bi bi-cart me-2"></i>Panier (<span id="panierCountMobile">0</span>)
            </h6>
            <div>
                <strong class="text-success me-3" id="panierTotalMobile">0,00 FCFA</strong>
                <button class="btn btn-sm btn-outline-danger" onclick="viderPanier()">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm flex-fill" onclick="togglePanierDetails()">
                <i class="bi bi-eye me-1"></i>Voir détails
            </button>
            <a href="/pharma-app/caissier/facture.php" class="btn btn-success btn-sm flex-fill">
                <i class="bi bi-receipt me-1"></i>Facturer
            </a>
        </div>
        <div id="panierDetailsMobile" style="display: none;" class="mt-2 border-top pt-2">
            <div id="panierContentMobile" style="max-height: 150px; overflow-y: auto; font-size: 0.875rem;">
                <!-- Articles du panier mobile -->
            </div>
        </div>
    </div>
</div>

<script>
let panier = JSON.parse(localStorage.getItem('panier') || '[]');
let currentMedicament = null;

function voirDetails(medicament) {
    currentMedicament = medicament;
    document.getElementById('modalTitle').textContent = medicament.nom;
    
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6>Informations générales</h6>
                <table class="table table-sm">
                    <tr><td><strong>Nom:</strong></td><td>${medicament.nom}</td></tr>
                    <tr><td><strong>Code-barres:</strong></td><td>${medicament.code_barre || 'Non renseigné'}</td></tr>
                    <tr><td><strong>Catégorie:</strong></td><td>${medicament.categorie_nom || 'Non classé'}</td></tr>
                    <tr><td><strong>Fournisseur:</strong></td><td>${medicament.fournisseur_nom || 'Non renseigné'}</td></tr>
                    <tr><td><strong>Unité:</strong></td><td>${medicament.unite}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Stock et prix</h6>
                <table class="table table-sm">
                    <tr><td><strong>Prix de vente:</strong></td><td class="text-success">${formatPrice(medicament.prix_vente)}</td></tr>
                    <tr><td><strong>Stock total:</strong></td><td><span class="badge ${medicament.stock_total <= 0 ? 'bg-danger' : (medicament.stock_total <= medicament.stock_minimum ? 'bg-warning' : 'bg-success')}">${medicament.stock_total}</span></td></tr>
                    <tr><td><strong>Ancien stock:</strong></td><td>${medicament.ancien_stock || 0}</td></tr>
                    <tr><td><strong>Nouveau stock:</strong></td><td>${medicament.nouveau_stock || 0}</td></tr>
                    <tr><td><strong>Stock minimum:</strong></td><td>${medicament.stock_minimum}</td></tr>
                    <tr><td><strong>Date expiration active:</strong></td><td>${medicament.date_expiration_active ? formatDate(medicament.date_expiration_active) : 'Non renseignée'}</td></tr>
                </table>
            </div>
        </div>
        ${medicament.description ? `<div class="mt-3"><h6>Description</h6><p>${medicament.description}</p></div>` : ''}
    `;
    
    const ajouterBtn = document.getElementById('ajouterPanierBtn');
    if (medicament.stock_total > 0) {
        ajouterBtn.style.display = 'block';
    } else {
        ajouterBtn.style.display = 'none';
    }
    
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}

function ajouterAuPanier(id, nom, prix) {
    const existant = panier.find(item => item.id === id);
    if (existant) {
        existant.quantite++;
    } else {
        panier.push({ id, nom, prix, quantite: 1 });
    }
    
    localStorage.setItem('panier', JSON.stringify(panier));
    mettreAJourPanier();
    
    // Notification
    const toast = document.createElement('div');
    toast.className = 'toast position-fixed top-0 end-0 m-3';
    toast.innerHTML = `
        <div class="toast-header">
            <i class="bi bi-check-circle text-success me-2"></i>
            <strong class="me-auto">Ajouté au panier</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">${nom} ajouté au panier</div>
    `;
    document.body.appendChild(toast);
    new bootstrap.Toast(toast).show();
    setTimeout(() => toast.remove(), 3000);
}

function ajouterAuPanierFromModal() {
    if (currentMedicament) {
        ajouterAuPanier(currentMedicament.id, currentMedicament.nom, currentMedicament.prix_vente);
        bootstrap.Modal.getInstance(document.getElementById('detailsModal')).hide();
    }
}

function retirerDuPanier(id) {
    panier = panier.filter(item => item.id !== id);
    localStorage.setItem('panier', JSON.stringify(panier));
    mettreAJourPanier();
}

function viderPanier() {
    if (confirm('Vider le panier ?')) {
        panier = [];
        localStorage.setItem('panier', JSON.stringify(panier));
        mettreAJourPanier();
    }
}

function mettreAJourPanier() {
    const panierFlottant = document.getElementById('panierFlottant');
    const panierMobile = document.getElementById('panierMobile');
    const panierCount = document.getElementById('panierCount');
    const panierCountMobile = document.getElementById('panierCountMobile');
    const panierContent = document.getElementById('panierContent');
    const panierContentMobile = document.getElementById('panierContentMobile');
    const panierTotal = document.getElementById('panierTotal');
    const panierTotalMobile = document.getElementById('panierTotalMobile');
    
    if (panier.length === 0) {
        panierFlottant.style.display = 'none';
        panierMobile.style.display = 'none';
        return;
    }
    
    panierFlottant.style.display = 'block';
    panierMobile.style.display = 'block';
    
    const totalItems = panier.reduce((sum, item) => sum + item.quantite, 0);
    const total = panier.reduce((sum, item) => sum + (item.prix * item.quantite), 0);
    
    panierCount.textContent = panier.reduce((sum, item) => sum + item.quantite, 0);
    panierCountMobile.textContent = totalItems;
    
    const panierHtml = panier.map(item => `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="flex-grow-1">
                <small><strong class="text-truncate d-block" style="max-width: 120px;" title="${item.nom}">${item.nom}</strong></small>
                <br><small class="text-muted">${item.quantite} x ${formatPrice(item.prix)}</small>
            </div>
            <button class="btn btn-sm btn-outline-danger" onclick="retirerDuPanier(${item.id})">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `).join('');
    
    panierContent.innerHTML = panierHtml;
    panierContentMobile.innerHTML = panierHtml;
    
    panierTotal.textContent = formatPrice(total);
    panierTotalMobile.textContent = formatPrice(total);
}

function togglePanierDetails() {
    const details = document.getElementById('panierDetailsMobile');
    details.style.display = details.style.display === 'none' ? 'block' : 'none';
}

function resetFilters() {
    document.getElementById('categorieFilter').value = '';
    document.getElementById('stockFilter').value = '';
    document.querySelector('.search-input').value = '';
    filterTable();
}

function filterTable() {
    const searchValue = document.querySelector('.search-input').value.toLowerCase();
    const categorieValue = document.getElementById('categorieFilter').value;
    const stockValue = document.getElementById('stockFilter').value;
    const rows = document.querySelectorAll('#medicamentsTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const categorie = row.dataset.categorie;
        const stockStatus = row.dataset.stockStatus;
        
        let show = true;
        
        if (searchValue && !text.includes(searchValue)) show = false;
        if (categorieValue && categorie !== categorieValue) show = false;
        if (stockValue && stockStatus !== stockValue) show = false;
        
        row.style.display = show ? '' : 'none';
    });
}

function formatPrice(price) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'XOF',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(price);
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('fr-FR');
}

// Event listeners
document.getElementById('categorieFilter').addEventListener('change', filterTable);
document.getElementById('stockFilter').addEventListener('change', filterTable);

// Initialiser le panier au chargement
document.addEventListener('DOMContentLoaded', () => {
    mettreAJourPanier();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>