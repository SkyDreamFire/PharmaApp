<?php
$pageTitle = 'Facturation';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

$db = Database::getInstance();
$message = '';
$messageType = '';

// Traitement de la création de facture
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_facture') {
    $client_id = intval($_POST['client_id'] ?? 0) ?: null;
    $mode_paiement = $_POST['mode_paiement'] ?? 'especes';
    $remise = floatval($_POST['remise'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $articles = json_decode($_POST['articles'] ?? '[]', true);
    
    if (empty($articles)) {
        $message = 'Aucun article sélectionné.';
        $messageType = 'danger';
    } else {
        $db->beginTransaction();
        try {
            // Calculer le montant total
            $montant_total = 0;
            foreach ($articles as $article) {
                $montant_total += $article['prix'] * $article['quantite'];
            }
            $montant_total -= $remise;
            
            // Générer le numéro de facture
            $numero_facture = generateInvoiceNumber();
            
            // Créer la vente
            $vente_query = "INSERT INTO ventes (numero_facture, client_id, user_id, montant_total, remise, mode_paiement, notes) 
                           VALUES (:numero_facture, :client_id, :user_id, :montant_total, :remise, :mode_paiement, :notes)";
            $vente_params = [
                ':numero_facture' => $numero_facture,
                ':client_id' => $client_id,
                ':user_id' => $_SESSION['user_id'],
                ':montant_total' => $montant_total,
                ':remise' => $remise,
                ':mode_paiement' => $mode_paiement,
                ':notes' => $notes
            ];
            
            if ($db->execute($vente_query, $vente_params)) {
                $vente_id = $db->lastInsertId();
                
                // Ajouter les détails de vente et mettre à jour le stock
                foreach ($articles as $article) {
                    // Ajouter le détail
                    $detail_query = "INSERT INTO vente_details (vente_id, medicament_id, quantite, prix_unitaire, sous_total) 
                                    VALUES (:vente_id, :medicament_id, :quantite, :prix_unitaire, :sous_total)";
                    $detail_params = [
                        ':vente_id' => $vente_id,
                        ':medicament_id' => $article['id'],
                        ':quantite' => $article['quantite'],
                        ':prix_unitaire' => $article['prix'],
                        ':sous_total' => $article['prix'] * $article['quantite']
                    ];
                    $db->execute($detail_query, $detail_params);
                    
                    // Utiliser la fonction FEFO pour la sortie de stock
                    if (!effectuerSortieFEFO($db, $article['id'], $article['quantite'], $_SESSION['user_id'], 'Vente - Facture ' . $numero_facture)) {
                        throw new Exception('Erreur lors de la sortie FEFO pour ' . $article['nom']);
                    }
                }
                
                $db->commit();
                $message = 'Facture créée avec succès. N° ' . $numero_facture;
                $messageType = 'success';
                
                // Redirection vers l'impression
                echo "<script>
                    setTimeout(() => {
                        window.open('/pharma-app/api/ventes.php?action=print&id=$vente_id', '_blank');
                    }, 1000);
                </script>";
            } else {
                throw new Exception('Erreur lors de la création de la vente');
            }
        } catch (Exception $e) {
            $db->rollback();
            $message = 'Erreur lors de la création de la facture: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Récupérer les données nécessaires
$medicaments = $db->select("
    SELECT m.*, c.nom as categorie_nom,
           (m.ancien_stock + m.nouveau_stock) as stock_total
    FROM medicaments m
    LEFT JOIN categories c ON m.categorie_id = c.id
    WHERE m.actif = 1 AND (m.ancien_stock + m.nouveau_stock) > 0
    ORDER BY m.nom ASC
");

$clients = $db->select("
    SELECT id, nom, prenom, telephone, email
    FROM clients 
    WHERE actif = 1 
    ORDER BY nom ASC, prenom ASC
");

// Historique des ventes récentes (si demandé)
$historique = [];
if (isset($_GET['history'])) {
    $historique = $db->select("
        SELECT v.*, c.nom as client_nom, c.prenom as client_prenom,
               COUNT(vd.id) as nb_articles
        FROM ventes v
        LEFT JOIN clients c ON v.client_id = c.id
        LEFT JOIN vente_details vd ON v.id = vd.vente_id
        WHERE v.user_id = :user_id
        GROUP BY v.id
        ORDER BY v.date_vente DESC
        LIMIT 20
    ", [':user_id' => $_SESSION['user_id']]);
}
?>

<div class="page-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">
                    <i class="bi bi-receipt me-2"></i>
                    Facturation
                </h1>
                <p class="page-subtitle">Créez et gérez vos factures de vente</p>
            </div>
            <div>
                <a href="?history=1" class="btn btn-outline-primary text-light me-2">
                    <i class="bi bi-clock-history me-2"></i>
                    Historique
                </a>
                <button class="btn btn-light" onclick="nouvelleFacture()">
                    <i class="bi bi-plus-lg me-2"></i>
                    Nouvelle Facture
                </button>
            </div>
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

<?php if (isset($_GET['history'])): ?>
    <!-- Historique des ventes -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-clock-history me-2"></i>
                Mes Ventes Récentes (<?= count($historique) ?>)
            </h5>
            <a href="/pharma-app/caissier/facture.php" class="btn btn-primary">
                <i class="bi bi-plus-lg me-2"></i>
                Nouvelle Facture
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>N° Facture</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Articles</th>
                            <th>Montant</th>
                            <th>Paiement</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historique as $vente): ?>
                            <tr>
                                <td><strong><?= escape($vente['numero_facture']) ?></strong></td>
                                <td><?= formatDateTime($vente['date_vente']) ?></td>
                                <td>
                                    <?php if ($vente['client_nom']): ?>
                                        <?= escape($vente['client_nom'] . ' ' . $vente['client_prenom']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Vente directe</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary"><?= $vente['nb_articles'] ?></span></td>
                                <td><strong class="text-success"><?= formatPrice($vente['montant_total']) ?></strong></td>
                                <td>
                                    <span class="badge <?= 
                                        $vente['mode_paiement'] === 'especes' ? 'bg-success' : 
                                        ($vente['mode_paiement'] === 'carte' ? 'bg-primary' : 'bg-info') 
                                    ?>">
                                        <?= ucfirst($vente['mode_paiement']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="voirDetails(<?= $vente['id'] ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="imprimerFacture(<?= $vente['id'] ?>)">
                                        <i class="bi bi-printer"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Interface de facturation -->
    <div class="row">
        <!-- Sélection des produits -->
        <div class="col-lg-7 col-xl-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-capsule me-2"></i>
                        Sélection des Médicaments
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Recherche -->
                    <div class="mb-3">
                        <div class="search-box">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" class="form-control" id="searchMedicament" placeholder="Rechercher un médicament...">
                        </div>
                    </div>
                    
                    <!-- Liste des médicaments -->
                    <div class="row" id="medicamentsList">
                        <?php foreach ($medicaments as $medicament): ?>
                            <?php 
                            $stock_total = getStockTotal($medicament);
                            $date_active = getDateExpirationActive($medicament);
                            ?>
                            <div class="col-sm-6 col-md-4 col-lg-6 col-xl-4 mb-3 medicament-item" data-nom="<?= strtolower($medicament['nom']) ?>">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title text-truncate" title="<?= escape($medicament['nom']) ?>"><?= escape($medicament['nom']) ?></h6>
                                        <p class="card-text">
                                            <?php if ($medicament['categorie_nom']): ?>
                                                <small class="text-muted d-block"><?= escape($medicament['categorie_nom']) ?></small>
                                            <?php endif; ?>
                                            <br><strong class="text-success"><?= formatPrice($medicament['prix_vente']) ?></strong>
                                            <br><span class="badge bg-info"><?= $stock_total ?> <?= escape($medicament['unite']) ?></span>
                                            <?php if ($date_active): ?>
                                                <br><small class="text-warning d-block">Exp: <?= formatDate($date_active) ?></small>
                                            <?php endif; ?>
                                        </p>
                                        <button class="btn btn-primary btn-sm w-100" 
                                                onclick="ajouterAuPanier(<?= $medicament['id'] ?>, '<?= escape($medicament['nom']) ?>', <?= $medicament['prix_vente'] ?>, <?= $stock_total ?>)">
                                            <i class="bi bi-cart-plus me-1"></i>Ajouter
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Panier et facturation -->
        <div class="col-lg-5 col-xl-4">
            <div class="card sticky-top" style="top: 100px;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-cart me-2"></i>
                        Panier (<span id="panierCount">0</span>)
                    </h5>
                    <button class="btn btn-sm btn-outline-danger" onclick="viderPanier()">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div id="panierContent" class="mb-3" style="max-height: 250px; overflow-y: auto;">
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-cart-x" style="font-size: 3rem;"></i>
                            <p class="mt-2">Panier vide</p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Informations client -->
                    <div class="mb-3">
                        <label for="client_id" class="form-label">Client (optionnel)</label>
                        <select class="form-select" id="client_id">
                            <option value="">Vente directe</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>"><?= escape($client['nom'] . ' ' . $client['prenom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Mode de paiement -->
                    <div class="mb-3">
                        <label for="mode_paiement" class="form-label">Mode de paiement</label>
                        <select class="form-select" id="mode_paiement">
                            <option value="especes">Espèces</option>
                            <option value="carte">Carte bancaire</option>
                            <option value="cheque">Chèque</option>
                            <option value="virement">Virement</option>
                        </select>
                    </div>
                    
                    <!-- Remise -->
                    <div class="mb-3">
                        <label for="remise" class="form-label">Remise (FCFA)</label>
                        <input type="number" class="form-control" id="remise" step="0.01" min="0" value="0" onchange="calculerTotal()">
                    </div>
                    
                    <!-- Notes -->
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" rows="2"></textarea>
                    </div>
                    
                    <hr>
                    
                    <!-- Total -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <strong>Total:</strong>
                        <h5 class="text-success mb-0" id="totalFacture">0,00 FCFA</h5>
                    </div>
                    
                    <!-- Bouton de validation -->
                    <button class="btn btn-success w-100 btn-lg" id="btnValider" onclick="validerFacture()" disabled>
                        <i class="bi bi-check-lg me-2"></i>
                        Valider la Facture
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
let panier = [];
let selectedClientId = localStorage.getItem('selectedClientId');

// Pré-sélectionner le client si défini
if (selectedClientId) {
    document.getElementById('client_id').value = selectedClientId;
    localStorage.removeItem('selectedClientId');
}

function ajouterAuPanier(id, nom, prix, stock) {
    const existant = panier.find(item => item.id === id);
    if (existant) {
        if (existant.quantite < stock) {
            existant.quantite++;
        } else {
            alert('Stock insuffisant');
            return;
        }
    } else {
        panier.push({ id, nom, prix, quantite: 1, stock });
    }
    
    mettreAJourPanier();
}

function retirerDuPanier(id) {
    panier = panier.filter(item => item.id !== id);
    mettreAJourPanier();
}

function modifierQuantite(id, quantite) {
    const item = panier.find(item => item.id === id);
    if (item) {
        if (quantite > 0 && quantite <= item.stock) {
            item.quantite = quantite;
        } else if (quantite <= 0) {
            retirerDuPanier(id);
            return;
        } else {
            alert('Stock insuffisant');
            return;
        }
    }
    mettreAJourPanier();
}

function viderPanier() {
    if (confirm('Vider le panier ?')) {
        panier = [];
        mettreAJourPanier();
    }
}

function mettreAJourPanier() {
    const panierContent = document.getElementById('panierContent');
    const panierCount = document.getElementById('panierCount');
    const btnValider = document.getElementById('btnValider');
    
    panierCount.textContent = panier.reduce((sum, item) => sum + item.quantite, 0);
    
    if (panier.length === 0) {
        panierContent.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="bi bi-cart-x" style="font-size: 3rem;"></i>
                <p class="mt-2">Panier vide</p>
            </div>
        `;
        btnValider.disabled = true;
    } else {
        panierContent.innerHTML = panier.map(item => `
            <div class="d-flex justify-content-between align-items-start mb-2 p-2 border rounded">
                <div class="flex-grow-1">
                    <small><strong class="text-truncate d-block" style="max-width: 120px;" title="${item.nom}">${item.nom}</strong></small>
                    <small class="text-muted d-block">${formatPrice(item.prix)} x 
                        <input type="number" class="form-control form-control-sm d-inline-block mt-1" 
                               style="width: 50px; font-size: 0.75rem;" value="${item.quantite}" min="1" max="${item.stock}"
                               onchange="modifierQuantite(${item.id}, this.value)">
                    </small>
                </div>
                <div class="text-end ms-2">
                    <small><strong>${formatPrice(item.prix * item.quantite)}</strong></small>
                    <br><button class="btn btn-sm btn-outline-danger mt-1" onclick="retirerDuPanier(${item.id})" style="font-size: 0.7rem; padding: 0.25rem 0.5rem;">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');
        btnValider.disabled = false;
    }
    
    calculerTotal();
}

function calculerTotal() {
    const sousTotal = panier.reduce((sum, item) => sum + (item.prix * item.quantite), 0);
    const remise = parseFloat(document.getElementById('remise').value) || 0;
    const total = Math.max(0, sousTotal - remise);
    
    document.getElementById('totalFacture').textContent = formatPrice(total);
}

function validerFacture() {
    if (panier.length === 0) {
        alert('Le panier est vide');
        return;
    }
    
    if (confirm('Confirmer la création de cette facture ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="create_facture">
            <input type="hidden" name="client_id" value="${document.getElementById('client_id').value}">
            <input type="hidden" name="mode_paiement" value="${document.getElementById('mode_paiement').value}">
            <input type="hidden" name="remise" value="${document.getElementById('remise').value}">
            <input type="hidden" name="notes" value="${document.getElementById('notes').value}">
            <input type="hidden" name="articles" value='${JSON.stringify(panier)}'>
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function nouvelleFacture() {
    viderPanier();
    document.getElementById('client_id').value = '';
    document.getElementById('mode_paiement').value = 'especes';
    document.getElementById('remise').value = '0';
    document.getElementById('notes').value = '';
}

// Recherche de médicaments
document.getElementById('searchMedicament').addEventListener('input', function() {
    const search = this.value.toLowerCase();
    const items = document.querySelectorAll('.medicament-item');
    
    items.forEach(item => {
        const nom = item.dataset.nom;
        if (nom.includes(search)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
});

function formatPrice(price) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'XOF',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(price);
}

function voirDetails(venteId) {
    window.open(`/pharma-app/api/ventes.php?action=details&id=${venteId}`, '_blank');
}

function imprimerFacture(venteId) {
    window.open(`/pharma-app/api/ventes.php?action=print&id=${venteId}`, '_blank');
}

// Initialiser
document.addEventListener('DOMContentLoaded', () => {
    mettreAJourPanier();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>