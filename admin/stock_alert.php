<?php
$pageTitle = 'Alertes de Stock';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

// Vérifier le rôle directeur
if (!checkRole('directeur')) {
    header('Location: /pharma-app/caissier/dashboard.php');
    exit();
}

$db = Database::getInstance();

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

// Statistiques
$stats = [
    'stock_critique' => count(array_filter($alertes, fn($a) => $a['stock_actuel'] == 0)),
    'stock_faible' => count(array_filter($alertes, fn($a) => $a['stock_actuel'] > 0)),
    'expires_bientot' => count(array_filter($expirations, fn($e) => $e['jours_restants'] > 0)),
    'expires' => count(array_filter($expirations, fn($e) => $e['jours_restants'] <= 0))
];
?>

<div class="page-header">
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Alertes de Stock
        </h1>
        <p class="page-subtitle">Surveillez les stocks critiques et les expirations</p>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-danger text-white">
                    <i class="bi bi-x-circle"></i>
                </div>
                <h3 class="stats-number text-danger"><?= $stats['stock_critique'] ?></h3>
                <p class="stats-label">Stock épuisé</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-warning text-white">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <h3 class="stats-number text-warning"><?= $stats['stock_faible'] ?></h3>
                <p class="stats-label">Stock faible</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-info text-white">
                    <i class="bi bi-clock"></i>
                </div>
                <h3 class="stats-number text-info"><?= $stats['expires_bientot'] ?></h3>
                <p class="stats-label">Expire bientôt</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-dark text-white">
                    <i class="bi bi-calendar-x"></i>
                </div>
                <h3 class="stats-number text-dark"><?= $stats['expires'] ?></h3>
                <p class="stats-label">Expiré</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Alertes de stock -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                    Alertes de Stock (<?= count($alertes) ?>)
                </h5>
                <?php if (!empty($alertes)): ?>
                    <button class="btn btn-outline-primary btn-sm">
                        <a href="/pharma-app/admin/commandes_fournisseurs.php" style="color: white; text-decoration:none"><i class="bi bi-cart-plus me-1"></i>Générer commande</a>
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($alertes)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                        <h4 class="mt-3 text-success">Aucune alerte de stock</h4>
                        <p class="text-muted">Tous vos médicaments ont un stock suffisant</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Médicament</th>
                                    <th>Catégorie</th>
                                    <th>Stock</th>
                                    <th>Fournisseur</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alertes as $alerte): ?>
                                    <tr class="<?= $alerte['stock_actuel'] == 0 ? 'table-danger' : 'table-warning' ?>">
                                        <td>
                                            <div>
                                                <strong><?= escape($alerte['nom']) ?></strong>
                                                <?php if ($alerte['code_barre']): ?>
                                                    <br><small class="text-muted"><?= escape($alerte['code_barre']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?= $alerte['categorie_nom'] ? '<span class="badge bg-secondary">' . escape($alerte['categorie_nom']) . '</span>' : '-' ?>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="badge <?= $alerte['stock_actuel'] == 0 ? 'bg-danger' : 'bg-warning' ?>">
                                                    <?= $alerte['stock_actuel'] ?> <?= escape($alerte['unite']) ?>
                                                </span>
                                                <br><small class="text-muted">Min: <?= $alerte['stock_minimum'] ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($alerte['fournisseur_nom']): ?>
                                                <div>
                                                    <strong><?= escape($alerte['fournisseur_nom']) ?></strong>
                                                    <?php if ($alerte['fournisseur_tel']): ?>
                                                        <br><a href="tel:<?= escape($alerte['fournisseur_tel']) ?>" class="text-decoration-none">
                                                            <i class="bi bi-telephone me-1"></i><?= escape($alerte['fournisseur_tel']) ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Aucun</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="/pharma-app/admin/produits.php" class="btn btn-outline-primary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ($alerte['fournisseur_email']): ?>
                                                    <a href="mailto:<?= escape($alerte['fournisseur_email']) ?>?subject=Commande <?= escape($alerte['nom']) ?>" 
                                                       class="btn btn-outline-success">
                                                        <i class="bi bi-envelope"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Alertes d'expiration -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-x text-danger me-2"></i>
                    Expirations (<?= count($expirations) ?>)
                </h5>
            </div>
            <div class="card-body">
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
</div>

<!-- Actions rapides -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-lightning me-2"></i>
            Actions Rapides
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-3">
                <a href="/pharma-app/admin/produits.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-capsule me-2"></i>
                    Gérer les médicaments
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="/pharma-app/admin/fournisseurs.php" class="btn btn-outline-success w-100">
                    <i class="bi bi-truck me-2"></i>
                    Contacter fournisseurs
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <button class="btn btn-outline-info w-100" onclick="exportAlertes()">
                    <i class="bi bi-download me-2"></i>
                    Exporter alertes
                </button>
            </div>
            <div class="col-md-3 mb-3">
                <button class="btn btn-outline-warning w-100" onclick="envoyerRapport()">
                    <i class="bi bi-envelope me-2"></i>
                    Envoyer rapport
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function genererCommande() {
    if (confirm('Générer une liste de commande basée sur les alertes de stock ?')) {
        // Ici vous pourriez implémenter la génération d'un fichier de commande
        alert('Fonctionnalité à implémenter : génération de commande');
    }
}

function exportAlertes() {
    window.open('/pharma-app/api/stock.php?action=export_alerts', '_blank');
}

function envoyerRapport() {
    if (confirm('Envoyer un rapport d\'alertes par email ?')) {
        fetch('/pharma-app/api/stock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'send_report'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Rapport envoyé avec succès');
            } else {
                alert('Erreur lors de l\'envoi du rapport');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de l\'envoi du rapport');
        });
    }
}

// Actualisation automatique toutes les 5 minutes
setInterval(() => {
    location.reload();
}, 300000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>