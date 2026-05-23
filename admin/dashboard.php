<?php
$pageTitle = 'Dashboard Directeur';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

// Vérifier le rôle directeur
if (!checkRole('directeur')) {
    header('Location: /pharma-app/caissier/dashboard.php');
    exit();
}

$db = Database::getInstance();

// Statistiques générales
$stats = [
    'total_medicaments' => $db->select("SELECT COUNT(*) as count FROM medicaments WHERE actif = 1")[0]['count'],
    'total_fournisseurs' => $db->select("SELECT COUNT(*) as count FROM fournisseurs WHERE actif = 1")[0]['count'],
    'total_users' => $db->select("SELECT COUNT(*) as count FROM users WHERE actif = 1")[0]['count'],
    'ventes_aujourd_hui' => $db->select("SELECT COUNT(*) as count FROM ventes WHERE DATE(date_vente) = CURDATE()")[0]['count'],
    'ca_aujourd_hui' => $db->select("SELECT COALESCE(SUM(montant_total), 0) as total FROM ventes WHERE DATE(date_vente) = CURDATE()")[0]['total'],
    'ca_mois' => $db->select("SELECT COALESCE(SUM(montant_total), 0) as total FROM ventes WHERE MONTH(date_vente) = MONTH(CURDATE()) AND YEAR(date_vente) = YEAR(CURDATE())")[0]['total']
];

// Alertes de stock
$alertes_stock = $db->select("SELECT * FROM stock_alerts ORDER BY stock_actuel ASC LIMIT 5");

// Récupérer les alertes de stock
$alertes = $db->select("
    SELECT m.*, 
           c.nom as categorie_nom,
           f.nom as fournisseur_nom,
           f.telephone as fournisseur_tel,
           f.email as fournisseur_email
    FROM medicaments m
    LEFT JOIN categories c ON m.categorie_id = c.id
    LEFT JOIN fournisseurs f ON m.fournisseur_id = f.id
    WHERE m.stock_actuel <= m.stock_minimum AND m.actif = 1
    ORDER BY (m.stock_actuel / NULLIF(m.stock_minimum, 0)) ASC, m.nom ASC
");

// Médicaments expirés ou qui expirent bientôt
$expirations = $db->select("
    SELECT m.*, 
           c.nom as categorie_nom,
           DATEDIFF(m.date_expiration, CURDATE()) as jours_restants
    FROM medicaments m
    LEFT JOIN categories c ON m.categorie_id = c.id
    WHERE m.date_expiration IS NOT NULL 
    AND m.date_expiration <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND m.actif = 1
    ORDER BY m.date_expiration ASC
");
// Dernières ventes
$dernieres_ventes = $db->select("
    SELECT v.*, u.nom, u.prenom, c.nom as client_nom, c.prenom as client_prenom
    FROM ventes v
    LEFT JOIN users u ON v.user_id = u.id
    LEFT JOIN clients c ON v.client_id = c.id
    ORDER BY v.date_vente DESC
    LIMIT 5
");

// Médicaments les plus vendus (cette semaine)
$top_medicaments = $db->select("
    SELECT m.nom, SUM(vd.quantite) as total_vendu, SUM(vd.sous_total) as ca_total
    FROM vente_details vd
    JOIN medicaments m ON vd.medicament_id = m.id
    JOIN ventes v ON vd.vente_id = v.id
    WHERE v.date_vente >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY m.id, m.nom
    ORDER BY total_vendu DESC
    LIMIT 5
");
?>

<div class="page-header">
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="bi bi-speedometer2 me-2"></i>
            Dashboard Directeur
        </h1>
        <p class="page-subtitle">Vue d'ensemble de votre pharmacie</p>
    </div>
</div>

<div class="alert-container"></div>

<!-- Statistiques principales -->
<div class="row mb-4 g-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card stats-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stats-icon bg-primary text-white me-3 flex-shrink-0">
                    <i class="bi bi-capsule"></i>
                </div>
                <div class="flex-grow-1">
                    <h2 class="stats-number text-primary mb-1"><?= number_format($stats['total_medicaments']) ?></h2>
                    <p class="stats-label mb-0">Médicaments</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6 col-lg-3">
        <div class="card stats-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stats-icon bg-success text-white me-3 flex-shrink-0">
                    <i class="bi bi-truck"></i>
                </div>
                <div class="flex-grow-1">
                    <h2 class="stats-number text-success mb-1"><?= number_format($stats['total_fournisseurs']) ?></h2>
                    <p class="stats-label mb-0">Fournisseurs</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6 col-lg-3">
        <div class="card stats-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stats-icon bg-info text-white me-3 flex-shrink-0">
                    <i class="bi bi-people"></i>
                </div>
                <div class="flex-grow-1">
                    <h2 class="stats-number text-info mb-1"><?= number_format($stats['total_users']) ?></h2>
                    <p class="stats-label mb-0">Utilisateurs</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6 col-lg-3">
        <div class="card stats-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stats-icon bg-warning text-white me-3 flex-shrink-0">
                    <i class="bi bi-cart"></i>
                </div>
                <div class="flex-grow-1">
                    <h2 class="stats-number text-warning mb-1"><?= number_format($stats['ventes_aujourd_hui']) ?></h2>
                    <p class="stats-label mb-0">Ventes aujourd'hui</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chiffre d'affaires -->
<div class="row mb-4 g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header card-gradient-success">
                <h5 class="mb-0">
                    <i class="bi bi-currency-euro me-2"></i>
                    <span class="d-none d-sm-inline">CA Aujourd'hui</span>
                    <span class="d-sm-none">Aujourd'hui</span>
                </h5>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <h2 class="display-6 display-md-4 text-success mb-0">
                    <?= formatPrice($stats['ca_aujourd_hui']) ?>
                </h2>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header card-gradient-info">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-month me-2"></i>
                    <span class="d-none d-sm-inline">CA Ce Mois</span>
                    <span class="d-sm-none">Ce Mois</span>
                </h5>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <h2 class="display-6 display-md-4 text-info mb-0">
                    <?= formatPrice($stats['ca_mois']) ?>
                </h2>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Alertes de stock -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                    Alertes de Stock
                </h5>
                <a href="/pharma-app/admin/stock_alert.php" class="btn btn-sm btn-outline-primary text-light">
                    Voir tout
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($alertes_stock)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">Aucune alerte de stock</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($alertes_stock as $alerte): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center stock-alert">
                                <div>
                                    <h6 class="mb-1"><?= escape($alerte['nom']) ?></h6>
                                    <small class="text-muted"><?= escape($alerte['fournisseur']) ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-danger"><?= $alerte['stock_actuel'] ?></span>
                                    <small class="d-block text-muted">Min: <?= $alerte['stock_minimum'] ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <br>  
                <?php if (empty($expirations)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">Aucune expiration proche</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($expirations as $expiration): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?= escape($expiration['nom']) ?></h6>
                                    <small class="text-muted"><?= escape($expiration['categorie_nom']) ?></small>
                                    <br>
                                    <small class="<?= $expiration['jours_restants'] <= 0 ? 'text-danger' : 'text-warning' ?>">
                                        <?php if ($expiration['jours_restants'] <= 0): ?>
                                            <i class="bi bi-x-circle me-1"></i>Expiré
                                        <?php elseif ($expiration['jours_restants'] <= 7): ?>
                                            <i class="bi bi-exclamation-triangle me-1"></i>Expire dans <?= $expiration['jours_restants'] ?> jour(s)
                                        <?php else: ?>
                                            <i class="bi bi-clock me-1"></i>Expire le <?= formatDate($expiration['date_expiration']) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <span class="badge <?= $expiration['jours_restants'] <= 0 ? 'bg-danger' : 'bg-warning' ?>">
                                    <?= $expiration['stock_actuel'] ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Dernières ventes -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-clock-history me-2"></i>
                    Dernières Ventes
                </h5>
                <a href="/pharma-app/admin/ventes.php" class="btn btn-sm btn-outline-primary text-light">
                    Voir tout
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($dernieres_ventes)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-cart-x text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">Aucune vente récente</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($dernieres_ventes as $vente): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?= escape($vente['numero_facture']) ?></h6>
                                    <small class="text-muted">
                                        Par: <?= escape($vente['nom'] . ' ' . $vente['prenom']) ?>
                                        <?php if ($vente['client_nom']): ?>
                                            <br>Client: <?= escape($vente['client_nom'] . ' ' . $vente['client_prenom']) ?>
                                        <?php endif; ?>
                                    </small>
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
</div>

<!-- Top médicaments -->
<?php if (!empty($top_medicaments)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-graph-up me-2"></i>
                    Top Médicaments (7 derniers jours)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Médicament</th>
                                <th class="text-center">Quantité Vendue</th>
                                <th class="text-end">Chiffre d'Affaires</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_medicaments as $index => $medicament): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-primary me-2"><?= $index + 1 ?></span>
                                            <?= escape($medicament['nom']) ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?= number_format($medicament['total_vendu']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <strong><?= formatPrice($medicament['ca_total']) ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>