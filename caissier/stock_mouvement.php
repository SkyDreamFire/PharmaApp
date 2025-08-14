<?php
$pageTitle = 'Mouvements de Stock';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

$db = Database::getInstance();
$message = '';
$messageType = '';

// Traitement des mouvements de stock
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_mouvement') {
        $medicament_id = intval($_POST['medicament_id'] ?? 0);
        $type_mouvement = $_POST['type_mouvement'] ?? '';
        $quantite = intval($_POST['quantite'] ?? 0);
        $motif = trim($_POST['motif'] ?? '');
        $numero_lot = trim($_POST['numero_lot'] ?? '');
        
        if ($medicament_id <= 0 || $quantite <= 0 || empty($motif)) {
            $message = 'Veuillez remplir tous les champs obligatoires.';
            $messageType = 'danger';
        } else {
            // Récupérer le stock actuel
            $medicament = $db->select("SELECT stock_actuel, nom FROM medicaments WHERE id = :id", [':id' => $medicament_id])[0] ?? null;
            
            if (!$medicament) {
                $message = 'Médicament introuvable.';
                $messageType = 'danger';
            } else {
                $stock_avant = $medicament['stock_actuel'];
                
                // Calculer le nouveau stock
                if ($type_mouvement === 'entree') {
                    $stock_apres = $stock_avant + $quantite;
                } elseif ($type_mouvement === 'sortie') {
                    if ($quantite > $stock_avant) {
                        $message = 'Quantité insuffisante en stock (' . $stock_avant . ' disponible).';
                        $messageType = 'danger';
                    } else {
                        $stock_apres = $stock_avant - $quantite;
                    }
                } else { // ajustement
                    $stock_apres = $quantite;
                    $quantite = abs($quantite - $stock_avant);
                }
                
                if (!isset($stock_apres)) {
                    // Erreur déjà définie
                } else {
                    $db->beginTransaction();
                    try {
                        // Enregistrer le mouvement
                        $mouvement_query = "INSERT INTO stock_mouvements (medicament_id, type_mouvement, quantite, quantite_avant, quantite_apres, user_id, motif, numero_lot) 
                                           VALUES (:medicament_id, :type_mouvement, :quantite, :quantite_avant, :quantite_apres, :user_id, :motif, :numero_lot)";
                        $mouvement_params = [
                            ':medicament_id' => $medicament_id,
                            ':type_mouvement' => $type_mouvement,
                            ':quantite' => $quantite,
                            ':quantite_avant' => $stock_avant,
                            ':quantite_apres' => $stock_apres,
                            ':user_id' => $_SESSION['user_id'],
                            ':motif' => $motif,
                            ':numero_lot' => $numero_lot ?: null
                        ];
                        
                        if ($db->execute($mouvement_query, $mouvement_params)) {
                            // Mettre à jour le stock du médicament
                            $update_query = "UPDATE medicaments SET stock_actuel = :stock_apres WHERE id = :id";
                            if ($db->execute($update_query, [':stock_apres' => $stock_apres, ':id' => $medicament_id])) {
                                $db->commit();
                                $message = 'Mouvement de stock enregistré avec succès.';
                                $messageType = 'success';
                            } else {
                                throw new Exception('Erreur lors de la mise à jour du stock');
                            }
                        } else {
                            throw new Exception('Erreur lors de l\'enregistrement du mouvement');
                        }
                    } catch (Exception $e) {
                        $db->rollback();
                        $message = 'Erreur: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
            }
        }
    }
}

// Récupérer les données
$medicaments = $db->select("
    SELECT id, nom, stock_actuel, unite, ancien_stock, nouveau_stock,
           date_expiration_ancien, date_expiration_nouveau,
           (ancien_stock + nouveau_stock) as stock_total
    FROM medicaments 
    WHERE actif = 1 
    ORDER BY nom ASC
");

$mouvements = $db->select("
    SELECT sm.*, m.nom as medicament_nom, m.unite, u.nom as user_nom, u.prenom as user_prenom
    FROM stock_mouvements sm
    JOIN medicaments m ON sm.medicament_id = m.id
    JOIN users u ON sm.user_id = u.id
    ORDER BY sm.date_mouvement DESC
    LIMIT 50
");

// Statistiques
$stats = [
    'entrees_jour' => $db->select("SELECT COALESCE(SUM(quantite), 0) as total FROM stock_mouvements WHERE type_mouvement = 'entree' AND DATE(date_mouvement) = CURDATE()")[0]['total'],
    'sorties_jour' => $db->select("SELECT COALESCE(SUM(quantite), 0) as total FROM stock_mouvements WHERE type_mouvement = 'sortie' AND DATE(date_mouvement) = CURDATE()")[0]['total'],
    'ajustements_jour' => $db->select("SELECT COUNT(*) as total FROM stock_mouvements WHERE type_mouvement = 'ajustement' AND DATE(date_mouvement) = CURDATE()")[0]['total'],
    'total_mouvements' => count($mouvements)
];
?>

<div class="page-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">
                    <i class="bi bi-arrow-up-down me-2"></i>
                    Mouvements de Stock
                </h1>
                <p class="page-subtitle">Gérez les entrées et sorties de stock</p>
            </div>
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#mouvementModal">
                <i class="bi bi-plus-lg me-2"></i>
                Nouveau Mouvement
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

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-success text-white">
                    <i class="bi bi-arrow-up"></i>
                </div>
                <h3 class="stats-number text-success"><?= number_format($stats['entrees_jour']) ?></h3>
                <p class="stats-label">Entrées aujourd'hui</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-danger text-white">
                    <i class="bi bi-arrow-down"></i>
                </div>
                <h3 class="stats-number text-danger"><?= number_format($stats['sorties_jour']) ?></h3>
                <p class="stats-label">Sorties aujourd'hui</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-warning text-white">
                    <i class="bi bi-arrow-left-right"></i>
                </div>
                <h3 class="stats-number text-warning"><?= number_format($stats['ajustements_jour']) ?></h3>
                <p class="stats-label">Ajustements aujourd'hui</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-info text-white">
                    <i class="bi bi-list"></i>
                </div>
                <h3 class="stats-number text-info"><?= number_format($stats['total_mouvements']) ?></h3>
                <p class="stats-label">Total mouvements</p>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <i class="bi bi-arrow-up text-success" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Entrée de Stock</h5>
                <p class="text-muted">Réception de marchandises</p>
                <button class="btn btn-success" onclick="nouveauMouvement('entree')">
                    <i class="bi bi-plus-lg me-2"></i>Nouvelle Entrée
                </button>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <i class="bi bi-arrow-down text-danger" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Sortie de Stock</h5>
                <p class="text-muted">Retrait ou perte</p>
                <button class="btn btn-danger" onclick="nouveauMouvement('sortie')">
                    <i class="bi bi-dash-lg me-2"></i>Nouvelle Sortie
                </button>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <i class="bi bi-arrow-left-right text-warning" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Ajustement</h5>
                <p class="text-muted">Correction d'inventaire</p>
                <button class="btn btn-warning" onclick="nouveauMouvement('ajustement')">
                    <i class="bi bi-gear me-2"></i>Ajustement
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Recherche -->
<div class="card mb-4">
    <div class="card-body">
        <div class="search-box">
            <i class="bi bi-search search-icon"></i>
            <input type="text" class="form-control search-input" placeholder="Rechercher un mouvement..." data-target="#mouvementsTable">
        </div>
    </div>
</div>

<!-- Historique des mouvements -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-clock-history me-2"></i>
            Historique des Mouvements (<?= count($mouvements) ?>)
        </h5>
        <button class="btn btn-outline-success btn-sm" onclick="exportMouvements()">
            <i class="bi bi-download me-1"></i>Exporter
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table" id="mouvementsTable">
                <thead>
                    <tr>
                        <th data-sort="date_mouvement">Date</th>
                        <th data-sort="medicament_nom">Médicament</th>
                        <th data-sort="type_mouvement">Type</th>
                        <th data-sort="quantite">Quantité</th>
                        <th data-sort="stock">Stock</th>
                        <th data-sort="user_nom">Utilisateur</th>
                        <th data-sort="motif">Motif</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mouvements as $mouvement): ?>
                        <tr>
                            <td data-sort="date_mouvement">
                                <?= formatDateTime($mouvement['date_mouvement']) ?>
                            </td>
                            <td data-sort="medicament_nom">
                                <strong><?= escape($mouvement['medicament_nom']) ?></strong>
                                <?php if ($mouvement['numero_lot']): ?>
                                    <br><small class="text-muted">Lot: <?= escape($mouvement['numero_lot']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td data-sort="type_mouvement">
                                <span class="badge <?= 
                                    $mouvement['type_mouvement'] === 'entree' ? 'bg-success' : 
                                    ($mouvement['type_mouvement'] === 'sortie' ? 'bg-danger' : 'bg-warning') 
                                ?>">
                                    <?php
                                    switch($mouvement['type_mouvement']) {
                                        case 'entree': echo '<i class="bi bi-arrow-up me-1"></i>Entrée'; break;
                                        case 'sortie': echo '<i class="bi bi-arrow-down me-1"></i>Sortie'; break;
                                        case 'ajustement': echo '<i class="bi bi-arrow-left-right me-1"></i>Ajustement'; break;
                                    }
                                    ?>
                                </span>
                            </td>
                            <td data-sort="quantite">
                                <strong><?= $mouvement['quantite'] ?></strong> <?= escape($mouvement['unite']) ?>
                            </td>
                            <td data-sort="stock">
                                <div>
                                    <small class="text-muted">Avant: <?= $mouvement['quantite_avant'] ?></small>
                                    <br><strong>Après: <?= $mouvement['quantite_apres'] ?></strong>
                                </div>
                            </td>
                            <td data-sort="user_nom">
                                <?= escape($mouvement['user_nom'] . ' ' . $mouvement['user_prenom']) ?>
                            </td>
                            <td data-sort="motif">
                                <?= escape($mouvement['motif']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal pour nouveau mouvement -->
<div class="modal fade" id="mouvementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nouveau Mouvement de Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_mouvement">
                    
                    <div class="mb-3">
                        <label for="medicament_id" class="form-label">Médicament *</label>
                        <select class="form-select" id="medicament_id" name="medicament_id" required onchange="afficherStock()">
                            <option value="">Sélectionner un médicament</option>
                            <?php foreach ($medicaments as $medicament): ?>
                                <option value="<?= $medicament['id'] ?>" 
                                        data-stock="<?= $medicament['stock_total'] ?>" 
                                        data-unite="<?= escape($medicament['unite']) ?>"
                                        data-ancien="<?= $medicament['ancien_stock'] ?>"
                                        data-nouveau="<?= $medicament['nouveau_stock'] ?>"
                                        data-date-ancien="<?= $medicament['date_expiration_ancien'] ?>"
                                        data-date-nouveau="<?= $medicament['date_expiration_nouveau'] ?>">
                                    <?= escape($medicament['nom']) ?> (Stock: <?= $medicament['stock_total'] ?> <?= escape($medicament['unite']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" id="stockActuel"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type_mouvement" class="form-label">Type de mouvement *</label>
                        <select class="form-select" id="type_mouvement" name="type_mouvement" required onchange="changerTypeMouvement()">
                            <option value="">Sélectionner le type</option>
                            <option value="entree">Entrée de stock</option>
                            <option value="sortie">Sortie de stock</option>
                            <option value="ajustement">Ajustement d'inventaire</option>
                        </select>
                        <div class="form-text" id="typeHelp"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quantite" class="form-label" id="quantiteLabel">Quantité *</label>
                        <input type="number" class="form-control" id="quantite" name="quantite" min="1" required>
                        <div class="form-text" id="quantiteHelp"></div>
                    </div>
                    
                    <!-- Champs spécifiques pour les entrées FEFO -->
                    <div id="entreeFEFO" style="display: none;">
                        <div class="mb-3">
                            <label for="date_expiration_lot" class="form-label">Date d'expiration du lot *</label>
                            <input type="date" class="form-control" id="date_expiration_lot" name="date_expiration_lot">
                            <div class="form-text">Cette date sera utilisée pour la gestion FEFO</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="type_stock" class="form-label">Type de stock *</label>
                            <select class="form-select" id="type_stock" name="type_stock">
                                <option value="nouveau">Nouveau stock</option>
                                <option value="ancien">Ancien stock</option>
                            </select>
                            <div class="form-text">Choisir où ajouter cette quantité</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="numero_lot" class="form-label">Numéro de lot</label>
                        <input type="text" class="form-control" id="numero_lot" name="numero_lot">
                    </div>
                    
                    <div class="mb-3">
                        <label for="motif" class="form-label">Motif *</label>
                        <textarea class="form-control" id="motif" name="motif" rows="3" required></textarea>
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
function nouveauMouvement(type) {
    document.getElementById('type_mouvement').value = type;
    changerTypeMouvement();
    new bootstrap.Modal(document.getElementById('mouvementModal')).show();
}

function afficherStock() {
    const select = document.getElementById('medicament_id');
    const option = select.options[select.selectedIndex];
    const stockActuel = document.getElementById('stockActuel');
    
    if (option.value) {
        const stock = option.dataset.stock;
        const unite = option.dataset.unite;
        const ancien = option.dataset.ancien;
        const nouveau = option.dataset.nouveau;
        const dateAncien = option.dataset.dateAncien;
        const dateNouveau = option.dataset.dateNouveau;
        
        let stockInfo = `<i class="bi bi-info-circle me-1"></i>Stock total: <strong>${stock} ${unite}</strong>`;
        
        if (parseInt(ancien) > 0 || parseInt(nouveau) > 0) {
            stockInfo += `<br><small class="text-muted">`;
            if (parseInt(ancien) > 0) {
                stockInfo += `Ancien: ${ancien} ${unite}`;
                if (dateAncien) stockInfo += ` (exp: ${dateAncien})`;
            }
            if (parseInt(nouveau) > 0) {
                if (parseInt(ancien) > 0) stockInfo += ` | `;
                stockInfo += `Nouveau: ${nouveau} ${unite}`;
                if (dateNouveau) stockInfo += ` (exp: ${dateNouveau})`;
            }
            stockInfo += `</small>`;
        }
        
        stockActuel.innerHTML = stockInfo;
    } else {
        stockActuel.innerHTML = '';
    }
}

function changerTypeMouvement() {
    const type = document.getElementById('type_mouvement').value;
    const quantiteLabel = document.getElementById('quantiteLabel');
    const quantiteHelp = document.getElementById('quantiteHelp');
    const quantiteInput = document.getElementById('quantite');
    const typeHelp = document.getElementById('typeHelp');
    const entreeFEFO = document.getElementById('entreeFEFO');
    
    switch(type) {
        case 'entree':
            quantiteLabel.textContent = 'Quantité à ajouter *';
            quantiteHelp.textContent = 'Nombre d\'unités à ajouter au stock (sera ajouté selon le type choisi)';
            typeHelp.textContent = 'Ajout de stock avec gestion FEFO automatique';
            quantiteInput.min = '1';
            entreeFEFO.style.display = 'block';
            document.getElementById('date_expiration_lot').required = true;
            break;
        case 'sortie':
            quantiteLabel.textContent = 'Quantité à retirer *';
            quantiteHelp.textContent = 'Nombre d\'unités à retirer du stock (sortie FEFO automatique)';
            typeHelp.textContent = 'Sortie automatique selon l\'ordre FEFO (First Expired, First Out)';
            quantiteInput.min = '1';
            entreeFEFO.style.display = 'none';
            document.getElementById('date_expiration_lot').required = false;
            break;
        case 'ajustement':
            quantiteLabel.textContent = 'Nouveau stock *';
            quantiteHelp.textContent = 'Nouveau stock total après ajustement';
            typeHelp.textContent = 'Ajustement du stock total (répartition automatique)';
            quantiteInput.min = '0';
            entreeFEFO.style.display = 'none';
            document.getElementById('date_expiration_lot').required = false;
            break;
        default:
            quantiteLabel.textContent = 'Quantité *';
            quantiteHelp.textContent = '';
            typeHelp.textContent = '';
            quantiteInput.min = '1';
            entreeFEFO.style.display = 'none';
            document.getElementById('date_expiration_lot').required = false;
    }
}

function resetModal() {
    document.querySelector('#mouvementModal form').reset();
    document.querySelector('#mouvementModal form').classList.remove('was-validated');
    document.getElementById('stockActuel').innerHTML = '';
    document.getElementById('quantiteHelp').textContent = '';
    document.getElementById('typeHelp').textContent = '';
    document.getElementById('entreeFEFO').style.display = 'none';
}

function exportMouvements() {
    window.open('/pharma-app/api/stock.php?action=export_movements', '_blank');
}

document.getElementById('mouvementModal').addEventListener('hidden.bs.modal', resetModal);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>