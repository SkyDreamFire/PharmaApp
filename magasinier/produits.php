<?php
$pageTitle = 'Gestion des Médicaments';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

// Vérifier le rôle (Directeur ou Magasinier autorisé)
if (!checkRole('magasinier') && !checkRole('directeur')) {
    header('Location: /pharma-app/auth/login.php');
    exit();
}

$db = Database::getInstance();
$message = '';
$messageType = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? '';
        $nom = trim($_POST['nom'] ?? '');
        $code_barre = trim($_POST['code_barre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Générer automatiquement un code-barres si non fourni
        if (empty($code_barre)) {
            $code_barre = generateBarcode();
        }
        
        $prix_achat = floatval($_POST['prix_achat'] ?? 0);
        $prix_vente = floatval($_POST['prix_vente'] ?? 0);
        $stock_actuel = intval($_POST['stock_actuel'] ?? 0);
        $stock_minimum = intval($_POST['stock_minimum'] ?? 0);
        $unite = trim($_POST['unite'] ?? 'unité');
        $date_expiration = $_POST['date_expiration'] ?? null;
        $ancien_stock = intval($_POST['ancien_stock'] ?? 0);
        $date_expiration_ancien = $_POST['date_expiration_ancien'] ?? null;
        $nouveau_stock = intval($_POST['nouveau_stock'] ?? 0);
        $date_expiration_nouveau = $_POST['date_expiration_nouveau'] ?? null;
        $categorie_id = intval($_POST['categorie_id'] ?? 0) ?: null;
        $fournisseur_id = intval($_POST['fournisseur_id'] ?? 0) ?: null;
        
        if (empty($nom) || $prix_achat <= 0 || $prix_vente <= 0) {
            $message = 'Veuillez remplir tous les champs obligatoires.';
            $messageType = 'danger';
        } else {
            if ($action === 'add') {
                // Pour un nouveau médicament, tout va dans le nouveau stock
                $stock_total = $ancien_stock + $nouveau_stock;
                $query = "INSERT INTO medicaments (nom, code_barre, description, prix_achat, prix_vente, 
                         stock_actuel, stock_minimum, unite, date_expiration, ancien_stock, date_expiration_ancien,
                         nouveau_stock, date_expiration_nouveau, categorie_id, fournisseur_id) 
                         VALUES (:nom, :code_barre, :description, :prix_achat, :prix_vente, 
                         :stock_total, :stock_minimum, :unite, :date_expiration, :ancien_stock, :date_expiration_ancien,
                         :nouveau_stock, :date_expiration_nouveau, :categorie_id, :fournisseur_id)";
            } else {
                $stock_total = $ancien_stock + $nouveau_stock;
                $query = "UPDATE medicaments SET nom = :nom, code_barre = :code_barre, description = :description,
                         prix_achat = :prix_achat, prix_vente = :prix_vente, stock_actuel = :stock_actuel,
                         stock_minimum = :stock_minimum, unite = :unite, date_expiration = :date_expiration,
                         ancien_stock = :ancien_stock, date_expiration_ancien = :date_expiration_ancien,
                         nouveau_stock = :nouveau_stock, date_expiration_nouveau = :date_expiration_nouveau,
                         categorie_id = :categorie_id, fournisseur_id = :fournisseur_id 
                         WHERE id = :id";
            }
            
            $params = [
                ':nom' => $nom,
                ':code_barre' => $code_barre ?: null,
                ':description' => $description ?: null,
                ':prix_achat' => $prix_achat,
                ':prix_vente' => $prix_vente,
                ':stock_actuel' => $stock_total,
                ':stock_minimum' => $stock_minimum,
                ':unite' => $unite,
                ':date_expiration' => $date_expiration ?: null,
                ':ancien_stock' => $ancien_stock,
                ':date_expiration_ancien' => $date_expiration_ancien ?: null,
                ':nouveau_stock' => $nouveau_stock,
                ':date_expiration_nouveau' => $date_expiration_nouveau ?: null,
                ':categorie_id' => $categorie_id,
                ':fournisseur_id' => $fournisseur_id
            ];
            
            if ($action === 'edit') {
                $params[':id'] = $id;
            }
            
            if ($db->execute($query, $params)) {
                $message = $action === 'add' ? 'Médicament ajouté avec succès.' : 'Médicament modifié avec succès.';
                $messageType = 'success';
            } else {
                $message = 'Erreur lors de l\'enregistrement.';
                $messageType = 'danger';
            }
        }
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            if ($db->execute("UPDATE medicaments SET actif = 0 WHERE id = :id", [':id' => $id])) {
                $message = 'Médicament supprimé avec succès.';
                $messageType = 'success';
            } else {
                $message = 'Erreur lors de la suppression.';
                $messageType = 'danger';
            }
        }
    }
}

// Récupérer les données
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
$fournisseurs = $db->select("SELECT * FROM fournisseurs WHERE actif = 1 ORDER BY nom ASC");
?>

<div class="page-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">
                    <i class="bi bi-capsule me-2"></i>
                    Gestion des Médicaments (Magasin)
                </h1>
                <p class="page-subtitle">Gérez le catalogue des médicaments de la pharmacie</p>
            </div>
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#medicamentModal">
                <i class="bi bi-plus-lg me-2"></i>
                Nouveau Médicament
            </button>
        </div>
    </div>
</div>

<div class="alert-container">
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
            <?= escape($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
</div>

<!-- Filtres et recherche -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
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
            <div class="col-md-3">
                <select class="form-select" id="stockFilter">
                    <option value="">Tous les stocks</option>
                    <option value="faible">Stock faible</option>
                    <option value="vide">Stock vide</option>
                    <option value="normal">Stock normal</option>
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

<!-- Tableau des médicaments -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-list me-2"></i>
            Liste des Médicaments (<?= count($medicaments) ?>)
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table" id="medicamentsTable">
                <thead>
                    <tr>
                        <th data-sort="nom">Nom</th>
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
                        <tr data-categorie="<?= $medicament['categorie_id'] ?>" 
                            data-stock-status="<?= $medicament['stock_actuel'] <= 0 ? 'vide' : ($medicament['stock_actuel'] <= $medicament['stock_minimum'] ? 'faible' : 'normal') ?>">
                            <td data-sort="nom">
                                <div>
                                    <strong><?= escape($medicament['nom']) ?></strong>
                                    <?php if ($medicament['code_barre']): ?>
                                        <br><small class="text-muted"><?= escape($medicament['code_barre']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-sort="categorie">
                                <?= $medicament['categorie_nom'] ? '<span class="badge bg-secondary">' . escape($medicament['categorie_nom']) . '</span>' : '-' ?>
                            </td>
                            <td data-sort="prix_vente">
                                <div>
                                    <strong><?= formatPrice($medicament['prix_vente']) ?></strong>
                                    <br><small class="text-muted">Achat: <?= formatPrice($medicament['prix_achat']) ?></small>
                                </div>
                            </td>
                            <td data-sort="stock_actuel">
                                <?php 
                                $stock_total = getStockTotal($medicament);
                                $date_active = getDateExpirationActive($medicament);
                                ?>
                                <span class="badge <?= $stock_total <= 0 ? 'bg-danger' : ($stock_total <= $medicament['stock_minimum'] ? 'bg-warning' : 'bg-success') ?>">
                                    <?= $stock_total ?> <?= escape($medicament['unite']) ?>
                                </span>
                                <br><small class="text-muted">Min: <?= $medicament['stock_minimum'] ?></small>
                                <?php if ($date_active): ?>
                                    <br><small class="text-info">Expire: <?= formatDate($date_active) ?></small>
                                <?php endif; ?>
                            </td>
                            <td data-sort="fournisseur">
                                <?= $medicament['fournisseur_nom'] ? escape($medicament['fournisseur_nom']) : '-' ?>
                            </td>
                            <td data-sort="date_expiration">
                                <?php 
                                $date_active = getDateExpirationActive($medicament);
                                if ($date_active): ?>
                                    <?= formatDate($date_active) ?>
                                    <?php
                                    $today = new DateTime();
                                    $expiration = new DateTime($date_active);
                                    $diff = $today->diff($expiration);
                                    if ($expiration < $today): ?>
                                        <br><small class="text-danger">Expiré</small>
                                    <?php elseif ($diff->days <= 30): ?>
                                        <br><small class="text-warning">Expire bientôt</small>
                                    <?php endif; ?>
                                    
                                    <!-- Affichage des détails des stocks -->
                                    <?php if ($medicament['ancien_stock'] > 0 && $medicament['nouveau_stock'] > 0): ?>
                                        <br><small class="text-muted">
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
                                             onclick="editMedicament(<?= htmlspecialchars(json_encode($medicament)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce médicament ?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $medicament['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal pour ajouter/modifier un médicament -->
<div class="modal fade" id="medicamentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nouveau Médicament</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="modalAction" value="add">
                    <input type="hidden" name="id" id="modalId">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="nom" class="form-label">Nom du médicament *</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                        <div class="col-md-4">
                            <label for="code_barre" class="form-label">Code-barres</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="code_barre" name="code_barre" placeholder="Généré automatiquement si vide">
                                <button type="button" class="btn btn-outline-secondary" onclick="genererCodeBarre()">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                            <div class="form-text">Laissez vide pour génération automatique</div>
                        </div>
                        
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="prix_achat" class="form-label">Prix d'achat (FCFA) *</label>
                            <input type="number" class="form-control" id="prix_achat" name="prix_achat" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label for="prix_vente" class="form-label">Prix de vente (FCFA) *</label>
                            <input type="number" class="form-control" id="prix_vente" name="prix_vente" step="0.01" min="0" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="stock_actuel" class="form-label">Stock actuel</label>
                            <input type="number" class="form-control" id="stock_actuel" name="stock_actuel" min="0" value="0" readonly>
                            <div class="form-text">Calculé automatiquement (Ancien + Nouveau)</div>
                        </div>
                        <div class="col-md-4">
                            <label for="stock_minimum" class="form-label">Stock minimum</label>
                            <input type="number" class="form-control" id="stock_minimum" name="stock_minimum" min="0" value="10">
                        </div>
                        <div class="col-md-4">
                            <label for="unite" class="form-label">Unité</label>
                            <select class="form-select" id="unite" name="unite">
                                <option value="unité">Unité</option>
                                <option value="boîte">Boîte</option>
                                <option value="tube">Tube</option>
                                <option value="flacon">Flacon</option>
                                <option value="sachet">Sachet</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="date_expiration" class="form-label">Date d'expiration (référence)</label>
                            <input type="date" class="form-control" id="date_expiration" name="date_expiration">
                            <div class="form-text">Date d'expiration générale (optionnelle)</div>
                        </div>
                    </div>
                    
                    <!-- Section FEFO : Gestion des deux stocks -->
                    <div class="row g-3 mt-3">
                        <div class="col-12">
                            <h6 class="text-primary">
                                <i class="bi bi-clock-history me-2"></i>
                                Gestion FEFO - Ancien et Nouveau Stock
                            </h6>
                            <p class="text-muted small">
                                Le système vendra automatiquement en priorité le stock qui expire le plus tôt.
                            </p>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="ancien_stock" class="form-label">Quantité ancien stock</label>
                            <input type="number" class="form-control" id="ancien_stock" name="ancien_stock" min="0" value="0" onchange="calculerStockTotal()">
                        </div>
                        <div class="col-md-3">
                            <label for="date_expiration_ancien" class="form-label">Date expiration ancien</label>
                            <input type="date" class="form-control" id="date_expiration_ancien" name="date_expiration_ancien">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="nouveau_stock" class="form-label">Quantité nouveau stock</label>
                            <input type="number" class="form-control" id="nouveau_stock" name="nouveau_stock" min="0" value="0" onchange="calculerStockTotal()">
                        </div>
                        <div class="col-md-3">
                            <label for="date_expiration_nouveau" class="form-label">Date expiration nouveau</label>
                            <input type="date" class="form-control" id="date_expiration_nouveau" name="date_expiration_nouveau">
                        </div>
                    </div>
                    
                    <div class="row g-3 mt-2">
                        <div class="col-12">
                            <div class="alert alert-info" id="fefoInfo" style="display: none;">
                                <i class="bi bi-info-circle me-2"></i>
                                <span id="fefoMessage"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="categorie_id" class="form-label">Catégorie</label>
                            <select class="form-select" id="categorie_id" name="categorie_id">
                                <option value="">Aucune catégorie</option>
                                <?php foreach ($categories as $categorie): ?>
                                    <option value="<?= $categorie['id'] ?>"><?= escape($categorie['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="fournisseur_id" class="form-label">Fournisseur</label>
                            <select class="form-select" id="fournisseur_id" name="fournisseur_id">
                                <option value="">Aucun fournisseur</option>
                                <?php foreach ($fournisseurs as $fournisseur): ?>
                                    <option value="<?= $fournisseur['id'] ?>"><?= escape($fournisseur['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function genererCodeBarre() {
    const timestamp = Date.now().toString();
    const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
    const barcode = '340' + timestamp.slice(-6) + random;
    document.getElementById('code_barre').value = barcode;
}

function editMedicament(medicament) {
    document.getElementById('modalTitle').textContent = 'Modifier le médicament';
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('modalId').value = medicament.id;
    
    document.getElementById('nom').value = medicament.nom || '';
    document.getElementById('code_barre').value = medicament.code_barre || '';
    document.getElementById('description').value = medicament.description || '';
    document.getElementById('prix_achat').value = medicament.prix_achat || '';
    document.getElementById('prix_vente').value = medicament.prix_vente || '';
    document.getElementById('stock_actuel').value = medicament.stock_actuel || 0;
    document.getElementById('stock_minimum').value = medicament.stock_minimum || 10;
    document.getElementById('ancien_stock').value = medicament.ancien_stock || 0;
    document.getElementById('date_expiration_ancien').value = medicament.date_expiration_ancien || '';
    document.getElementById('nouveau_stock').value = medicament.nouveau_stock || 0;
    document.getElementById('date_expiration_nouveau').value = medicament.date_expiration_nouveau || '';
    document.getElementById('unite').value = medicament.unite || 'unité';
    document.getElementById('categorie_id').value = medicament.categorie_id || '';
    document.getElementById('fournisseur_id').value = medicament.fournisseur_id || '';
    document.getElementById('date_expiration').value = medicament.date_expiration || '';
    
    calculerStockTotal();
    
    new bootstrap.Modal(document.getElementById('medicamentModal')).show();
}

function resetModal() {
    document.getElementById('modalTitle').textContent = 'Nouveau Médicament';
    document.getElementById('modalAction').value = 'add';
    document.getElementById('modalId').value = '';
    document.querySelector('#medicamentModal form').reset();
    document.querySelector('#medicamentModal form').classList.remove('was-validated');
    calculerStockTotal();
}

function calculerStockTotal() {
    const ancienStock = parseInt(document.getElementById('ancien_stock').value) || 0;
    const nouveauStock = parseInt(document.getElementById('nouveau_stock').value) || 0;
    const stockTotal = ancienStock + nouveauStock;
    
    document.getElementById('stock_actuel').value = stockTotal;
    
    const dateAncien = document.getElementById('date_expiration_ancien').value;
    const dateNouveau = document.getElementById('date_expiration_nouveau').value;
    const fefoInfo = document.getElementById('fefoInfo');
    const fefoMessage = document.getElementById('fefoMessage');
    
    if (ancienStock > 0 && nouveauStock > 0 && dateAncien && dateNouveau) {
        const dateAncienObj = new Date(dateAncien);
        const dateNouveauObj = new Date(dateNouveau);
        
        fefoInfo.style.display = 'block';
        
        if (dateAncienObj <= dateNouveauObj) {
            fefoMessage.textContent = `Priorité FEFO : Ancien stock (${ancienStock} unités, expire le ${formatDateFr(dateAncien)}) sera vendu en premier.`;
        } else {
            fefoMessage.textContent = `Priorité FEFO : Nouveau stock (${nouveauStock} unités, expire le ${formatDateFr(dateNouveau)}) sera vendu en premier.`;
        }
    } else {
        fefoInfo.style.display = 'none';
    }
}

function formatDateFr(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('fr-FR');
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

document.getElementById('categorieFilter').addEventListener('change', filterTable);
document.getElementById('stockFilter').addEventListener('change', filterTable);
document.getElementById('ancien_stock').addEventListener('input', calculerStockTotal);
document.getElementById('nouveau_stock').addEventListener('input', calculerStockTotal);
document.getElementById('date_expiration_ancien').addEventListener('change', calculerStockTotal);
document.getElementById('date_expiration_nouveau').addEventListener('change', calculerStockTotal);

document.getElementById('medicamentModal').addEventListener('hidden.bs.modal', resetModal);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
