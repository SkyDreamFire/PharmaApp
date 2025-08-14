<?php
$pageTitle = 'Historique des Ventes';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

// Vérifier le rôle directeur
if (!checkRole('directeur')) {
    header('Location: /pharma-app/caissier/dashboard.php');
    exit();
}

$db = Database::getInstance();

// Filtres
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$user_id = $_GET['user_id'] ?? '';

// Statistiques
$stats_query = "
    SELECT 
        COUNT(*) as nb_ventes,
        COALESCE(SUM(montant_total), 0) as ca_total,
        COALESCE(AVG(montant_total), 0) as panier_moyen
    FROM ventes 
    WHERE DATE(date_vente) BETWEEN :date_debut AND :date_fin
";
$stats_params = [':date_debut' => $date_debut, ':date_fin' => $date_fin];

if ($user_id) {
    $stats_query .= " AND user_id = :user_id";
    $stats_params[':user_id'] = $user_id;
}

$stats = $db->select($stats_query, $stats_params)[0];

// Ventes détaillées
$ventes_query = "
    SELECT v.*, 
           u.nom as vendeur_nom, u.prenom as vendeur_prenom,
           c.nom as client_nom, c.prenom as client_prenom,
           COUNT(vd.id) as nb_articles
    FROM ventes v
    LEFT JOIN users u ON v.user_id = u.id
    LEFT JOIN clients c ON v.client_id = c.id
    LEFT JOIN vente_details vd ON v.id = vd.vente_id
    WHERE DATE(v.date_vente) BETWEEN :date_debut AND :date_fin
";
$ventes_params = [':date_debut' => $date_debut, ':date_fin' => $date_fin];

if ($user_id) {
    $ventes_query .= " AND v.user_id = :user_id";
    $ventes_params[':user_id'] = $user_id;
}

$ventes_query .= " GROUP BY v.id ORDER BY v.date_vente DESC";
$ventes = $db->select($ventes_query, $ventes_params);

// Liste des vendeurs pour le filtre
$vendeurs = $db->select("SELECT id, nom, prenom FROM users WHERE actif = 1 ORDER BY nom, prenom");

// Ventes par jour (pour le graphique)
$ventes_par_jour = $db->select("
    SELECT DATE(date_vente) as date, 
           COUNT(*) as nb_ventes,
           SUM(montant_total) as ca
    FROM ventes 
    WHERE DATE(date_vente) BETWEEN :date_debut AND :date_fin
    GROUP BY DATE(date_vente)
    ORDER BY date ASC
", [':date_debut' => $date_debut, ':date_fin' => $date_fin]);
?>

<div class="page-header">
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="bi bi-cart me-2"></i>
            Historique des Ventes
        </h1>
        <p class="page-subtitle">Analysez les performances de vente</p>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="date_debut" class="form-label">Date début</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= $date_debut ?>">
            </div>
            <div class="col-md-3">
                <label for="date_fin" class="form-label">Date fin</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= $date_fin ?>">
            </div>
            <div class="col-md-4">
                <label for="user_id" class="form-label">Vendeur</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">Tous les vendeurs</option>
                    <?php foreach ($vendeurs as $vendeur): ?>
                        <option value="<?= $vendeur['id'] ?>" <?= $user_id == $vendeur['id'] ? 'selected' : '' ?>>
                            <?= escape($vendeur['nom'] . ' ' . $vendeur['prenom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i>Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-primary text-white">
                    <i class="bi bi-cart-check"></i>
                </div>
                <h3 class="stats-number text-primary"><?= number_format($stats['nb_ventes']) ?></h3>
                <p class="stats-label">Ventes</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-success text-white">
                    <i class="bi bi-currency-euro"></i>
                </div>
                <h3 class="stats-number text-success"><?= formatPrice($stats['ca_total']) ?></h3>
                <p class="stats-label">Chiffre d'affaires</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-info text-white">
                    <i class="bi bi-basket"></i>
                </div>
                <h3 class="stats-number text-info"><?= formatPrice($stats['panier_moyen']) ?></h3>
                <p class="stats-label">Panier moyen</p>
            </div>
        </div>
    </div>
</div>

<!-- Graphique des ventes -->
<?php if (!empty($ventes_par_jour)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-graph-up me-2"></i>
            Évolution des Ventes
        </h5>
    </div>
    <div class="card-body">
        <canvas id="ventesChart" height="100"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- Recherche -->
<div class="card mb-4">
    <div class="card-body">
        <div class="search-box">
            <i class="bi bi-search search-icon"></i>
            <input type="text" class="form-control search-input" placeholder="Rechercher une vente..." data-target="#ventesTable">
        </div>
    </div>
</div>

<!-- Tableau des ventes -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-list me-2"></i>
            Liste des Ventes (<?= count($ventes) ?>)
        </h5>
        <button class="btn btn-outline-success btn-sm" onclick="exportVentes()">
            <i class="bi bi-download me-1"></i>Exporter
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table" id="ventesTable">
                <thead>
                    <tr>
                        <th data-sort="numero_facture">N° Facture</th>
                        <th data-sort="date_vente">Date</th>
                        <th data-sort="vendeur">Vendeur</th>
                        <th data-sort="client">Client</th>
                        <th data-sort="nb_articles">Articles</th>
                        <th data-sort="montant_total">Montant</th>
                        <th data-sort="mode_paiement">Paiement</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventes as $vente): ?>
                        <tr>
                            <td data-sort="numero_facture">
                                <strong><?= escape($vente['numero_facture']) ?></strong>
                            </td>
                            <td data-sort="date_vente">
                                <?= formatDateTime($vente['date_vente']) ?>
                            </td>
                            <td data-sort="vendeur">
                                <?= escape($vente['vendeur_nom'] . ' ' . $vente['vendeur_prenom']) ?>
                            </td>
                            <td data-sort="client">
                                <?php if ($vente['client_nom']): ?>
                                    <?= escape($vente['client_nom'] . ' ' . $vente['client_prenom']) ?>
                                <?php else: ?>
                                    <span class="text-muted">Vente directe</span>
                                <?php endif; ?>
                            </td>
                            <td data-sort="nb_articles">
                                <span class="badge bg-secondary"><?= $vente['nb_articles'] ?></span>
                            </td>
                            <td data-sort="montant_total">
                                <strong class="text-success"><?= formatPrice($vente['montant_total']) ?></strong>
                            </td>
                            <td data-sort="mode_paiement">
                                <span class="badge <?= 
                                    $vente['mode_paiement'] === 'especes' ? 'bg-success' : 
                                    ($vente['mode_paiement'] === 'carte' ? 'bg-primary' : 'bg-info') 
                                ?>">
                                    <?= ucfirst($vente['mode_paiement']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-outline-primary" onclick="voirDetails(<?= $vente['id'] ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="imprimerFacture(<?= $vente['id'] ?>)">
                                        <i class="bi bi-printer"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal détails vente -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de la vente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Contenu chargé dynamiquement -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Graphique des ventes
<?php if (!empty($ventes_par_jour)): ?>
const ctx = document.getElementById('ventesChart').getContext('2d');
const ventesChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: [<?= implode(',', array_map(fn($v) => '"' . formatDate($v['date']) . '"', $ventes_par_jour)) ?>],
        datasets: [{
            label: 'Nombre de ventes',
            data: [<?= implode(',', array_column($ventes_par_jour, 'nb_ventes')) ?>],
            borderColor: 'rgb(13, 110, 253)',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            tension: 0.1,
            yAxisID: 'y'
        }, {
            label: 'Chiffre d\'affaires (€)',
            data: [<?= implode(',', array_column($ventes_par_jour, 'ca')) ?>],
            borderColor: 'rgb(25, 135, 84)',
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            tension: 0.1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            x: {
                display: true,
                title: {
                    display: true,
                    text: 'Date'
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Nombre de ventes'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Chiffre d\'affaires (€)'
                },
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});
<?php endif; ?>

async function voirDetails(venteId) {
    try {
        const response = await fetch(`/pharma-app/api/ventes.php?action=details&id=${venteId}`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('detailsContent').innerHTML = data.html;
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        } else {
            alert('Erreur lors du chargement des détails');
        }
    } catch (error) {
        console.error('Erreur:', error);
        alert('Erreur lors du chargement des détails');
    }
}

function imprimerFacture(venteId) {
    window.open(`/pharma-app/api/ventes.php?action=print&id=${venteId}`, '_blank');
}

function exportVentes() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = `${window.location.pathname}?${params.toString()}`;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>