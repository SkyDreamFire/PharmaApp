<?php
$pageTitle = 'Dashboard Caissier';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

$db = Database::getInstance();

// Statistiques pour le caissier
$stats = [
    'ventes_aujourd_hui' => $db->select("SELECT COUNT(*) as count FROM ventes WHERE DATE(date_vente) = CURDATE() AND user_id = ?", [$_SESSION['user_id']])[0]['count'],
    'ca_aujourd_hui' => $db->select("SELECT COALESCE(SUM(montant_total), 0) as total FROM ventes WHERE DATE(date_vente) = CURDATE() AND user_id = ?", [$_SESSION['user_id']])[0]['total'],
    'total_medicaments' => $db->select("SELECT COUNT(*) as count FROM medicaments WHERE actif = 1")[0]['count'],
    'stock_alerts' => $db->select("SELECT COUNT(*) as count FROM stock_alerts")[0]['count']
];

// Mes ventes récentes
$mes_ventes = $db->select("
    SELECT v.*, c.nom as client_nom, c.prenom as client_prenom
    FROM ventes v
    LEFT JOIN clients c ON v.client_id = c.id
    WHERE v.user_id = ?
    ORDER BY v.date_vente DESC
    LIMIT 5
", [$_SESSION['user_id']]);

// Médicaments en alerte de stock
$alertes_stock = $db->select("SELECT * FROM stock_alerts ORDER BY stock_actuel ASC LIMIT 5");
?>

<div class="page-header">
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="bi bi-speedometer2 me-2"></i>
            Dashboard Caissier
        </h1>
        <p class="page-subtitle">Bonjour <?= escape($_SESSION['user_prenom']) ?>, bonne journée de travail !</p>
    </div>
</div>

<div class="alert-container"></div>

<!-- Statistiques principales -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="stats-icon bg-primary text-white">
                    <i class="bi bi-cart-check"></i>
                </div>
                <h2 class="stats-number text-primary"><?= number_format($stats['ventes_aujourd_hui']) ?></h2>
                <p class="stats-label">Mes ventes aujourd'hui</p>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="stats-icon bg-success text-white">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <h2 class="stats-number text-success"><?= formatPrice($stats['ca_aujourd_hui']) ?></h2>
                <p class="stats-label">Mon CA aujourd'hui</p>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="stats-icon bg-info text-white">
                    <i class="bi bi-capsule"></i>
                </div>
                <h2 class="stats-number text-info"><?= number_format($stats['total_medicaments']) ?></h2>
                <p class="stats-label">Médicaments disponibles</p>
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
                <p class="stats-label">Alertes de stock</p>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <a href="/pharma-app/caissier/facture.php" class="card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-receipt text-primary" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Nouvelle Vente</h5>
                <p class="text-muted">Créer une nouvelle facture</p>
            </div>
        </a>
    </div>
    
    <div class="col-md-3 mb-3">
        <a href="/pharma-app/caissier/clients.php" class="card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-person-plus text-success" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Nouveau Client</h5>
                <p class="text-muted">Ajouter un client</p>
            </div>
        </a>
    </div>
    
    <div class="col-md-3 mb-3">
        <a href="/pharma-app/caissier/produits.php" class="card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-search text-info" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Rechercher</h5>
                <p class="text-muted">Consulter les médicaments</p>
            </div>
        </a>
    </div>
    
    <div class="col-md-3 mb-3">
        <a href="/pharma-app/caissier/stock_mouvement.php" class="card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-arrow-down-up text-warning" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Mouvements Stock</h5>
                <p class="text-muted">Gérer les entrées/sorties</p>
            </div>
        </a>
    </div>
</div>

<div class="row">
    <!-- Mes dernières ventes -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-clock-history me-2"></i>
                    Mes Dernières Ventes
                </h5>
                <a href="/pharma-app/caissier/facture.php?history=1" class="btn btn-sm btn-outline-primary">
                    Voir tout
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($mes_ventes)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-cart-x text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">Aucune vente aujourd'hui</p>
                        <a href="/pharma-app/caissier/facture.php" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-2"></i>
                            Créer une vente
                        </a>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($mes_ventes as $vente): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?= escape($vente['numero_facture']) ?></h6>
                                    <?php if ($vente['client_nom']): ?>
                                        <small class="text-muted">
                                            Client: <?= escape($vente['client_nom'] . ' ' . $vente['client_prenom']) ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">Vente directe</small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <strong class="text-success"><?= formatPrice($vente['montant_total']) ?></strong>
                                    <small class="d-block text-muted"><?= formatDateTime($vente['date_vente']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Alertes de stock -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                    Alertes Stock
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($alertes_stock)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">Aucune alerte</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($alertes_stock as $alerte): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center stock-alert">
                                <div>
                                    <h6 class="mb-1"><?= escape($alerte['nom']) ?></h6>
                                    <small class="text-muted"><?= escape($alerte['categorie']) ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-danger"><?= $alerte['stock_actuel'] ?></span>
                                    <small class="d-block text-muted">Min: <?= $alerte['stock_minimum'] ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Informez le directeur de ces alertes
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Conseils du jour -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-lightbulb me-2"></i>
                    Conseils du jour
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="d-flex">
                            <i class="bi bi-shield-check text-success me-3" style="font-size: 1.5rem;"></i>
                            <div>
                                <h6>Vérification des dates</h6>
                                <small class="text-muted">Toujours vérifier les dates d'expiration avant la vente</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="d-flex">
                            <i class="bi bi-person-check text-info me-3" style="font-size: 1.5rem;"></i>
                            <div>
                                <h6>Service client</h6>
                                <small class="text-muted">Demander si le client a des allergies connues</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="d-flex">
                            <i class="bi bi-clipboard-data text-warning me-3" style="font-size: 1.5rem;"></i>
                            <div>
                                <h6>Suivi des stocks</h6>
                                <small class="text-muted">Signaler les ruptures de stock immédiatement</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>