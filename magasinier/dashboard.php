<?php
$pageTitle = 'Dashboard Magasinier';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

// Vérifier le rôle magasinier
if (!checkRole('magasinier')) {
    header('Location: /pharma-app/auth/login.php');
    exit();
}

$db = Database::getInstance();

// Statistiques pour le magasinier
$stats = [
    'total_medicaments' => $db->select("SELECT COUNT(*) as count FROM medicaments WHERE actif = 1")[0]['count'],
    'stock_alerts' => $db->select("SELECT COUNT(*) as count FROM stock_alerts")[0]['count'],
    'expirations_30_jours' => $db->select("SELECT COUNT(*) as count FROM medicaments WHERE date_expiration IS NOT NULL AND date_expiration <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND actif = 1")[0]['count'],
    'commandes_attente' => $db->select("SELECT COUNT(*) as count FROM commandes_fournisseurs WHERE statut = 'en_attente'")[0]['count']
];

// Ravitaillements récents (mouvements d'entrée)
$ravitaillements = $db->select("
    SELECT sm.*, m.nom as medicament_nom, m.unite, u.nom as user_nom, u.prenom as user_prenom
    FROM stock_mouvements sm
    JOIN medicaments m ON sm.medicament_id = m.id
    JOIN users u ON sm.user_id = u.id
    WHERE sm.type_mouvement = 'entree'
    ORDER BY sm.date_mouvement DESC
    LIMIT 5
");

// Médicaments en alerte de stock
$alertes_stock = $db->select("SELECT * FROM stock_alerts ORDER BY stock_actuel ASC LIMIT 5");
?>

<div class="page-header">
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="bi bi-speedometer2 me-2"></i>
            Dashboard Magasinier (Gestion des Stocks)
        </h1>
        <p class="page-subtitle">Bonjour <?= escape($_SESSION['user_prenom']) ?>, bon suivi logistique !</p>
    </div>
</div>

<div class="alert-container"></div>

<!-- Statistiques principales -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="stats-icon bg-primary text-white">
                    <i class="bi bi-capsule"></i>
                </div>
                <h2 class="stats-number text-primary"><?= number_format($stats['total_medicaments']) ?></h2>
                <p class="stats-label">Médicaments au catalogue</p>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="stats-icon bg-warning text-white">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <h2 class="stats-number text-warning"><?= number_format($stats['stock_alerts']) ?></h2>
                <p class="stats-label">Alertes de stock faible</p>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="stats-icon bg-danger text-white">
                    <i class="bi bi-calendar-x"></i>
                </div>
                <h2 class="stats-number text-danger"><?= number_format($stats['expirations_30_jours']) ?></h2>
                <p class="stats-label">Expirations imminentes (30 j)</p>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="stats-icon bg-info text-white">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <h2 class="stats-number text-info"><?= number_format($stats['commandes_attente']) ?></h2>
                <p class="stats-label">Commandes en attente</p>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides logistiques -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <a href="/pharma-app/magasinier/stock_mouvement.php" class="card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-plus-circle text-success" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Nouveau Ravitaillement</h5>
                <p class="text-muted">Enregistrer une entrée de stock</p>
            </div>
        </a>
    </div>
    
    <div class="col-md-3 mb-3">
        <a href="/pharma-app/magasinier/commandes_fournisseurs.php" class="card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-cart-plus text-primary" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Commander Fournisseur</h5>
                <p class="text-muted">Générer un bon de commande</p>
            </div>
        </a>
    </div>
    
    <div class="col-md-3 mb-3">
        <a href="/pharma-app/magasinier/produits.php" class="card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-capsule-pill text-info" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Gérer Catalogue</h5>
                <p class="text-muted">Créer ou éditer un médicament</p>
            </div>
        </a>
    </div>
    
    <div class="col-md-3 mb-3">
        <a href="/pharma-app/magasinier/fournisseurs.php" class="card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-truck text-warning" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Fournisseurs</h5>
                <p class="text-muted">Consulter les coordonnées</p>
            </div>
        </a>
    </div>
</div>

<div class="row">
    <!-- Ravitaillements récents -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-clock-history me-2"></i>
                    Ravitaillements Récents
                </h5>
                <a href="/pharma-app/magasinier/stock_mouvement.php" class="btn btn-sm btn-outline-primary">
                    Voir tout
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($ravitaillements)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-arrow-down-left-square text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">Aucun ravitaillement enregistré récemment</p>
                        <a href="/pharma-app/magasinier/stock_mouvement.php" class="btn btn-success">
                            <i class="bi bi-plus-lg me-2"></i>
                            Faire une entrée de stock
                        </a>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($ravitaillements as $mvt): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?= escape($mvt['medicament_nom']) ?></h6>
                                    <small class="text-muted">
                                        Lot: <?= escape($mvt['numero_lot'] ?: 'Non renseigné') ?> | Saisi par: <?= escape($mvt['user_nom'] . ' ' . $mvt['user_prenom']) ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <strong class="text-success">+<?= $mvt['quantite'] ?> <?= escape($mvt['unite']) ?></strong>
                                    <small class="d-block text-muted"><?= formatDateTime($mvt['date_mouvement']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Alertes de stock faible -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                    Alerte Ruptures
                </h5>
                <a href="/pharma-app/magasinier/commandes_fournisseurs.php" class="btn btn-sm btn-outline-danger">
                    Commander
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($alertes_stock)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">Aucun médicament en stock critique !</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($alertes_stock as $alerte): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?= escape($alerte['nom']) ?></h6>
                                    <small class="text-muted">Fourn: <?= escape($alerte['fournisseur'] ?: 'Non spécifié') ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-danger"><?= $alerte['stock_actuel'] ?></span>
                                    <small class="d-block text-muted">Min: <?= $alerte['stock_minimum'] ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
