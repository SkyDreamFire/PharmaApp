<?php
$pageTitle = 'Commandes Fournisseurs';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

// Vérifier le rôle (Directeur ou Magasinier)
if (!checkRole('magasinier') && !checkRole('directeur')) {
    header('Location: /pharma-app/auth/login.php');
    exit();
}

$db = Database::getInstance();
$message = '';
$messageType = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create_commande') {
        $fournisseur_id = intval($_POST['fournisseur_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $articles = json_decode($_POST['articles'] ?? '[]', true);
        
        if (empty($articles)) {
            $message = 'Aucun article sélectionné.';
            $messageType = 'danger';
        } elseif ($fournisseur_id <= 0) {
            $message = 'Veuillez sélectionner un fournisseur.';
            $messageType = 'danger';
        } else {
            $db->beginTransaction();
            try {
                // Calculer le montant total
                $montant_total = 0;
                foreach ($articles as $article) {
                    $montant_total += $article['prix'] * $article['quantite'];
                }
                
                // Générer le numéro de commande
                $numero_commande = 'CMD' . date('Ymd') . rand(1000, 9999);
                
                // Créer la commande
                $commande_query = "INSERT INTO commandes_fournisseurs (numero_commande, fournisseur_id, user_id, montant_total, notes) 
                               VALUES (:numero_commande, :fournisseur_id, :user_id, :montant_total, :notes)";
                $commande_params = [
                    ':numero_commande' => $numero_commande,
                    ':fournisseur_id' => $fournisseur_id,
                    ':user_id' => $_SESSION['user_id'],
                    ':montant_total' => $montant_total,
                    ':notes' => $notes
                ];
                
                if ($db->execute($commande_query, $commande_params)) {
                    $commande_id = $db->lastInsertId();
                    
                    // Ajouter les détails
                    foreach ($articles as $article) {
                        $detail_query = "INSERT INTO details_commande_fournisseurs (commande_id, medicament_id, quantite, prix_unitaire, sous_total) 
                                        VALUES (:commande_id, :medicament_id, :quantite, :prix_unitaire, :sous_total)";
                        $detail_params = [
                            ':commande_id' => $commande_id,
                            ':medicament_id' => $article['id'],
                            ':quantite' => $article['quantite'],
                            ':prix_unitaire' => $article['prix'],
                            ':sous_total' => $article['prix'] * $article['quantite']
                        ];
                        $db->execute($detail_query, $detail_params);
                    }
                    
                    $db->commit();
                    $message = 'Commande créée avec succès. N° ' . $numero_commande;
                    $messageType = 'success';
                } else {
                    throw new Exception('Erreur lors de la création de la commande');
                }
            } catch (Exception $e) {
                $db->rollback();
                $message = 'Erreur: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $commande_id = intval($_POST['commande_id'] ?? 0);
        $nouveau_statut = $_POST['statut'] ?? '';
        
        if ($commande_id > 0 && in_array($nouveau_statut, ['en_attente', 'confirmee', 'livree', 'annulee'])) {
            if ($db->execute("UPDATE commandes_fournisseurs SET statut = :statut WHERE id = :id", [
                ':statut' => $nouveau_statut,
                ':id' => $commande_id
            ])) {
                $message = 'Statut de la commande mis à jour avec succès.';
                $messageType = 'success';
            } else {
                $message = 'Erreur lors de la mise à jour du statut.';
                $messageType = 'danger';
            }
        }
    }
}

// Récupération des données
$medicaments = $db->select("
    SELECT m.id, m.nom, m.prix_vente, m.unite, c.nom as categorie_nom
    FROM medicaments m
    LEFT JOIN categories c ON m.categorie_id = c.id
    WHERE m.actif = 1
    ORDER BY m.nom ASC
");

$fournisseurs = $db->select("
    SELECT id, nom, contact_principal, telephone
    FROM fournisseurs 
    WHERE actif = 1 
    ORDER BY nom ASC
");

$isHistoryMode = isset($_GET['history']);

if ($isHistoryMode) {
    $commandes = $db->select("
        SELECT c.*, f.nom as fournisseur_nom,
               COUNT(d.id) as nb_articles
        FROM commandes_fournisseurs c
        LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
        LEFT JOIN details_commande_fournisseurs d ON c.id = d.commande_id
        GROUP BY c.id
        ORDER BY c.date_commande DESC
    ");
}
?>

<div class="page-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Commandes Fournisseurs
                </h1>
                <p class="page-subtitle">Gérez les commandes auprès de vos fournisseurs</p>
            </div>
            <div>
                <?php if ($isHistoryMode): ?>
                    <a href="commandes_fournisseurs.php" class="btn btn-primary text-light me-2">
                        <i class="bi bi-plus-lg me-2"></i>
                        Nouvelle Commande
                    </a>
                <?php else: ?>
                    <a href="?history=1" class="btn btn-light me-2 text-primary">
                        <i class="bi bi-clock-history me-2"></i>
                        Historique
                    </a>
                <?php endif; ?>
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

<?php if ($isHistoryMode): ?>
    <!-- Liste des commandes -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Liste des commandes</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>N° Commande</th>
                            <th>Date</th>
                            <th>Fournisseur</th>
                            <th>Articles</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commandes as $cmd): ?>
                            <tr>
                                <td><strong><?= escape($cmd['numero_commande']) ?></strong></td>
                                <td><?= formatDateTime($cmd['date_commande']) ?></td>
                                <td><?= escape($cmd['fournisseur_nom']) ?></td>
                                <td><span class="badge bg-secondary"><?= $cmd['nb_articles'] ?></span></td>
                                <td><strong class="text-primary"><?= number_format($cmd['montant_total'], 2, ',', ' ') ?> FCFA</strong></td>
                                <td>
                                    <?php 
                                        $badgeClass = 'bg-secondary';
                                        $icon = 'bi-clock';
                                        switch($cmd['statut']) {
                                            case 'en_attente': $badgeClass = 'bg-warning text-dark'; $icon = 'bi-hourglass-split'; break;
                                            case 'confirmee': $badgeClass = 'bg-info text-dark'; $icon = 'bi-check-circle'; break;
                                            case 'livree': $badgeClass = 'bg-success'; $icon = 'bi-box-seam'; break;
                                            case 'annulee': $badgeClass = 'bg-danger'; $icon = 'bi-x-circle'; break;
                                        }
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><i class="bi <?= $icon ?> me-1"></i><?= ucfirst(str_replace('_', ' ', $cmd['statut'])) ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="modifierStatut(<?= $cmd['id'] ?>, '<?= $cmd['statut'] ?>', '<?= escape($cmd['numero_commande']) ?>')" title="Modifier statut">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="/pharma-app/api/commandes.php?action=print&id=<?= $cmd['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Imprimer le bon de commande">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Modification Statut -->
    <div class="modal fade" id="statutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Modifier statut - <span id="statutCommandeNum"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="commande_id" id="statutCommandeId" value="">
                        
                        <div class="mb-3">
                            <label for="nouveauStatut" class="form-label">Nouveau statut</label>
                            <select class="form-select" id="nouveauStatut" name="statut" required>
                                <option value="en_attente">En attente</option>
                                <option value="confirmee">Confirmée</option>
                                <option value="livree">Livrée</option>
                                <option value="annulee">Annulée</option>
                            </select>
                            <div class="form-text">Si la commande est livrée, n'oubliez pas d'ajouter manuellement les stocks reçus dans "Mouvements de Stock".</div>
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
    function modifierStatut(id, statut, num) {
        document.getElementById('statutCommandeId').value = id;
        document.getElementById('statutCommandeNum').textContent = num;
        document.getElementById('nouveauStatut').value = statut;
        new bootstrap.Modal(document.getElementById('statutModal')).show();
    }
    </script>
    
<?php else: ?>
    <!-- Interface de Création de Commande -->
    <div class="row">
        <!-- Sélection des produits -->
        <div class="col-lg-7 col-xl-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-capsule me-2"></i>
                        Sélection des Médicaments à commander
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
                            <div class="col-sm-6 col-md-4 col-lg-6 col-xl-4 mb-3 medicament-item" data-nom="<?= strtolower($medicament['nom']) ?>">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title text-truncate" title="<?= escape($medicament['nom']) ?>"><?= escape($medicament['nom']) ?></h6>
                                        <p class="card-text">
                                            <?php if ($medicament['categorie_nom']): ?>
                                                <small class="text-muted d-block"><?= escape($medicament['categorie_nom']) ?></small>
                                            <?php endif; ?>
                                            Prix estimé : <strong class="text-success"><?= number_format($medicament['prix_vente'] * 0.7, 2, ',', ' ') ?> FCFA</strong>
                                        </p>
                                        <button class="btn btn-outline-primary btn-sm w-100" 
                                                onclick="ajouterAuPanier(<?= $medicament['id'] ?>, '<?= escape(addslashes($medicament['nom'])) ?>', <?= $medicament['prix_vente'] * 0.7 ?>)">
                                            <i class="bi bi-plus-lg me-1"></i>Ajouter à la commande
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Panier de commande -->
        <div class="col-lg-5 col-xl-4">
            <div class="card sticky-top" style="top: 100px;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>
                        Bon de commande (<span id="panierCount">0</span>)
                    </h5>
                    <button class="btn btn-sm btn-danger" onclick="viderPanier()">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div id="panierContent" class="mb-3" style="max-height: 250px; overflow-y: auto;">
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-cart-x" style="font-size: 3rem;"></i>
                            <p class="mt-2">Aucun article</p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Fournisseur -->
                    <div class="mb-3">
                        <label for="fournisseur_id" class="form-label">Fournisseur *</label>
                        <select class="form-select" id="fournisseur_id" required>
                            <option value="">Sélectionner un fournisseur</option>
                            <?php foreach ($fournisseurs as $fournisseur): ?>
                                <option value="<?= $fournisseur['id'] ?>"><?= escape($fournisseur['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Notes -->
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes de commande</label>
                        <textarea class="form-control" id="notes" rows="2" placeholder="Ex: Livraison urgente"></textarea>
                    </div>
                    
                    <hr>
                    
                    <!-- Total -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <strong>Montant estimé:</strong>
                        <h5 class="text-success mb-0" id="totalFacture">0 FCFA</h5>
                    </div>
                    
                    <!-- Bouton de validation -->
                    <button class="btn btn-primary w-100 btn-lg" id="btnValider" onclick="validerCommande()" disabled>
                        <i class="bi bi-send me-2"></i>
                        Générer la Commande
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    let panier = [];
    
    function formatPrice(price) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'XOF',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(price);
    }

    function ajouterAuPanier(id, nom, prix) {
        const existant = panier.find(item => item.id === id);
        if (existant) {
            existant.quantite++;
        } else {
            panier.push({ id, nom, prix, quantite: 1 });
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
            if (quantite > 0) {
                item.quantite = parseInt(quantite);
            } else {
                retirerDuPanier(id);
                return;
            }
        }
        mettreAJourPanier();
    }
    
    function modifierPrix(id, prix) {
        const item = panier.find(item => item.id === id);
        if (item && prix >= 0) {
            item.prix = parseFloat(prix);
        }
        mettreAJourPanier();
    }

    function viderPanier() {
        if (confirm('Vider la commande ?')) {
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
                    <p class="mt-2">Aucun article</p>
                </div>
            `;
            btnValider.disabled = true;
        } else {
            panierContent.innerHTML = panier.map(item => `
                <div class="d-flex justify-content-between align-items-start mb-2 p-2 border rounded">
                    <div class="flex-grow-1">
                        <small><strong class="text-truncate d-block" style="max-width: 120px;" title="${item.nom}">${item.nom}</strong></small>
                        <div class="mt-1 d-flex align-items-center">
                            <input type="number" class="form-control form-control-sm d-inline-block" 
                                   style="width: 55px; font-size: 0.75rem;" value="${item.quantite}" min="1"
                                   onchange="modifierQuantite(${item.id}, this.value)" title="Quantité">
                            <span class="mx-1">x</span>
                            <input type="number" class="form-control form-control-sm d-inline-block" 
                                   style="width: 80px; font-size: 0.75rem;" value="${item.prix}" min="0" step="0.01"
                                   onchange="modifierPrix(${item.id}, this.value)" title="Prix Unitaire (FCFA)">
                        </div>
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
        const total = panier.reduce((sum, item) => sum + (item.prix * item.quantite), 0);
        document.getElementById('totalFacture').textContent = formatPrice(total);
    }

    function validerCommande() {
        if (panier.length === 0) {
            alert('La commande est vide');
            return;
        }
        
        const fournisseurId = document.getElementById('fournisseur_id').value;
        if (!fournisseurId) {
            alert('Veuillez sélectionner un fournisseur');
            return;
        }
        
        if (confirm('Confirmer la création de cette commande fournisseur ?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="create_commande">
                <input type="hidden" name="fournisseur_id" value="${fournisseurId}">
                <input type="hidden" name="notes" value="${document.getElementById('notes').value}">
                <input type="hidden" name="articles" value='${JSON.stringify(panier)}'>
            `;
            document.body.appendChild(form);
            form.submit();
        }
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
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
