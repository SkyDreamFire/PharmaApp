<?php
$pageTitle = 'Gestion des Clients';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

$db = Database::getInstance();
$message = '';
$messageType = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? '';
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $date_naissance = $_POST['date_naissance'] ?? null;
        
        if (empty($nom) || empty($prenom)) {
            $message = 'Le nom et le prénom sont obligatoires.';
            $messageType = 'danger';
        } elseif ($email && !validateEmail($email)) {
            $message = 'Format d\'email invalide.';
            $messageType = 'danger';
        } else {
            if ($action === 'add') {
                $query = "INSERT INTO clients (nom, prenom, email, telephone, adresse, date_naissance) 
                         VALUES (:nom, :prenom, :email, :telephone, :adresse, :date_naissance)";
            } else {
                $query = "UPDATE clients SET nom = :nom, prenom = :prenom, email = :email,
                         telephone = :telephone, adresse = :adresse, date_naissance = :date_naissance 
                         WHERE id = :id";
            }
            
            $params = [
                ':nom' => $nom,
                ':prenom' => $prenom,
                ':email' => $email ?: null,
                ':telephone' => $telephone ?: null,
                ':adresse' => $adresse ?: null,
                ':date_naissance' => $date_naissance ?: null
            ];
            
            if ($action === 'edit') {
                $params[':id'] = $id;
            }
            
            if ($db->execute($query, $params)) {
                $message = $action === 'add' ? 'Client ajouté avec succès.' : 'Client modifié avec succès.';
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
            if ($db->execute("UPDATE clients SET actif = 0 WHERE id = :id", [':id' => $id])) {
                $message = 'Client supprimé avec succès.';
                $messageType = 'success';
            } else {
                $message = 'Erreur lors de la suppression.';
                $messageType = 'danger';
            }
        }
    }
}

// Récupérer les clients avec leurs statistiques
$clients = $db->select("
    SELECT c.*, 
           COUNT(v.id) as nb_achats,
           COALESCE(SUM(v.montant_total), 0) as total_achats,
           MAX(v.date_vente) as dernier_achat
    FROM clients c
    LEFT JOIN ventes v ON c.id = v.client_id
    WHERE c.actif = 1
    GROUP BY c.id
    ORDER BY c.nom ASC, c.prenom ASC
");
?>

<div class="page-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">
                    <i class="bi bi-people me-2"></i>
                    Gestion des Clients
                </h1>
                <p class="page-subtitle">Gérez votre base de clients</p>
            </div>
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#clientModal">
                <i class="bi bi-person-plus me-2"></i>
                Nouveau Client
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
                <div class="stats-icon bg-primary text-white">
                    <i class="bi bi-people"></i>
                </div>
                <h3 class="stats-number text-primary"><?= count($clients) ?></h3>
                <p class="stats-label">Clients actifs</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-success text-white">
                    <i class="bi bi-cart-check"></i>
                </div>
                <h3 class="stats-number text-success"><?= array_sum(array_column($clients, 'nb_achats')) ?></h3>
                <p class="stats-label">Total achats</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-info text-white">
                    <i class="bi bi-currency-euro"></i>
                </div>
                <h3 class="stats-number text-info"><?= formatPrice(array_sum(array_column($clients, 'total_achats'))) ?></h3>
                <p class="stats-label">CA total</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="stats-icon bg-warning text-white">
                    <i class="bi bi-star"></i>
                </div>
                <h3 class="stats-number text-warning"><?= count(array_filter($clients, fn($c) => $c['nb_achats'] >= 5)) ?></h3>
                <p class="stats-label">Clients fidèles</p>
            </div>
        </div>
    </div>
</div>

<!-- Recherche -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <div class="search-box">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="form-control search-input" placeholder="Rechercher un client..." data-target="#clientsTable">
                </div>
            </div>
            <div class="col-md-4 text-end">
                <span class="text-muted">Total: <?= count($clients) ?> client(s)</span>
            </div>
        </div>
    </div>
</div>

<!-- Liste des clients -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-list me-2"></i>
            Liste des Clients (<?= count($clients) ?>)
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table" id="clientsTable">
                <thead>
                    <tr>
                        <th data-sort="nom">Client</th>
                        <th data-sort="email">Contact</th>
                        <th data-sort="date_naissance">Âge</th>
                        <th data-sort="nb_achats">Achats</th>
                        <th data-sort="total_achats">CA</th>
                        <th data-sort="dernier_achat">Dernier achat</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td data-sort="nom">
                                <div class="d-flex align-items-center">
                                    <div class="avatar me-3">
                                        <i class="bi bi-person-circle text-muted" style="font-size: 2rem;"></i>
                                    </div>
                                    <div>
                                        <strong><?= escape($client['nom'] . ' ' . $client['prenom']) ?></strong>
                                        <?php if ($client['nb_achats'] >= 5): ?>
                                            <br><small class="text-warning">
                                                <i class="bi bi-star-fill me-1"></i>Client fidèle
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td data-sort="email">
                                <?php if ($client['email']): ?>
                                    <div>
                                        <i class="bi bi-envelope me-1"></i>
                                        <a href="mailto:<?= escape($client['email']) ?>"><?= escape($client['email']) ?></a>
                                    </div>
                                <?php endif; ?>
                                <?php if ($client['telephone']): ?>
                                    <div>
                                        <i class="bi bi-telephone me-1"></i>
                                        <a href="tel:<?= escape($client['telephone']) ?>"><?= escape($client['telephone']) ?></a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!$client['email'] && !$client['telephone']): ?>
                                    <span class="text-muted">Non renseigné</span>
                                <?php endif; ?>
                            </td>
                            <td data-sort="date_naissance">
                                <?php if ($client['date_naissance']): ?>
                                    <?= calculateAge($client['date_naissance']) ?> ans
                                    <br><small class="text-muted"><?= formatDate($client['date_naissance']) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">Non renseigné</span>
                                <?php endif; ?>
                            </td>
                            <td data-sort="nb_achats">
                                <span class="badge bg-primary"><?= $client['nb_achats'] ?></span>
                            </td>
                            <td data-sort="total_achats">
                                <strong class="text-success"><?= formatPrice($client['total_achats']) ?></strong>
                            </td>
                            <td data-sort="dernier_achat">
                                <?php if ($client['dernier_achat']): ?>
                                    <?= formatDate($client['dernier_achat']) ?>
                                <?php else: ?>
                                    <span class="text-muted">Aucun achat</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="editClient(<?= htmlspecialchars(json_encode($client)) ?>)"
                                            data-bs-toggle="tooltip" 
                                            title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" 
                                            onclick="voirHistorique(<?= $client['id'] ?>)"
                                            data-bs-toggle="tooltip" 
                                            title="Historique">
                                        <i class="bi bi-clock-history"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" 
                                            onclick="nouvelleVente(<?= $client['id'] ?>)"
                                            data-bs-toggle="tooltip" 
                                            title="Nouvelle vente">
                                        <i class="bi bi-cart-plus"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce client ?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $client['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="tooltip" 
                                                title="Supprimer">
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

<!-- Modal pour ajouter/modifier un client -->
<div class="modal fade" id="clientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nouveau Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="modalAction" value="add">
                    <input type="hidden" name="id" id="modalId">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nom" class="form-label">Nom *</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                        <div class="col-md-6">
                            <label for="prenom" class="form-label">Prénom *</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-6">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="date_naissance" class="form-label">Date de naissance</label>
                            <input type="date" class="form-control" id="date_naissance" name="date_naissance">
                        </div>
                        
                        <div class="col-12">
                            <label for="adresse" class="form-label">Adresse</label>
                            <textarea class="form-control" id="adresse" name="adresse" rows="3"></textarea>
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

<!-- Modal historique client -->
<div class="modal fade" id="historiqueModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Historique des achats</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="historiqueContent">
                <!-- Contenu chargé dynamiquement -->
            </div>
        </div>
    </div>
</div>

<script>
function editClient(client) {
    document.getElementById('modalTitle').textContent = 'Modifier le client';
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('modalId').value = client.id;
    
    document.getElementById('nom').value = client.nom || '';
    document.getElementById('prenom').value = client.prenom || '';
    document.getElementById('email').value = client.email || '';
    document.getElementById('telephone').value = client.telephone || '';
    document.getElementById('adresse').value = client.adresse || '';
    document.getElementById('date_naissance').value = client.date_naissance || '';
    
    new bootstrap.Modal(document.getElementById('clientModal')).show();
}

function resetModal() {
    document.getElementById('modalTitle').textContent = 'Nouveau Client';
    document.getElementById('modalAction').value = 'add';
    document.getElementById('modalId').value = '';
    document.querySelector('#clientModal form').reset();
    document.querySelector('#clientModal form').classList.remove('was-validated');
}

async function voirHistorique(clientId) {
    try {
        const response = await fetch(`/pharma-app/api/ventes.php?action=client_history&client_id=${clientId}`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('historiqueContent').innerHTML = data.html;
            new bootstrap.Modal(document.getElementById('historiqueModal')).show();
        } else {
            alert('Erreur lors du chargement de l\'historique');
        }
    } catch (error) {
        console.error('Erreur:', error);
        alert('Erreur lors du chargement de l\'historique');
    }
}

function nouvelleVente(clientId) {
    // Stocker l'ID du client sélectionné
    localStorage.setItem('selectedClientId', clientId);
    // Rediriger vers la page de facturation
    window.location.href = '/pharma-app/caissier/facture.php';
}

document.getElementById('clientModal').addEventListener('hidden.bs.modal', resetModal);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>