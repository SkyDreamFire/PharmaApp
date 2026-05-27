<?php
$pageTitle = 'Gestion des Fournisseurs';
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
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $code_postal = trim($_POST['code_postal'] ?? '');
        $contact_principal = trim($_POST['contact_principal'] ?? '');
        
        if (empty($nom)) {
            $message = 'Le nom du fournisseur est obligatoire.';
            $messageType = 'danger';
        } else {
            if ($action === 'add') {
                $query = "INSERT INTO fournisseurs (nom, email, telephone, adresse, ville, code_postal, contact_principal) 
                         VALUES (:nom, :email, :telephone, :adresse, :ville, :code_postal, :contact_principal)";
            } else {
                $query = "UPDATE fournisseurs SET nom = :nom, email = :email, telephone = :telephone,
                         adresse = :adresse, ville = :ville, code_postal = :code_postal, 
                         contact_principal = :contact_principal WHERE id = :id";
            }
            
            $params = [
                ':nom' => $nom,
                ':email' => $email ?: null,
                ':telephone' => $telephone ?: null,
                ':adresse' => $adresse ?: null,
                ':ville' => $ville ?: null,
                ':code_postal' => $code_postal ?: null,
                ':contact_principal' => $contact_principal ?: null
            ];
            
            if ($action === 'edit') {
                $params[':id'] = $id;
            }
            
            if ($db->execute($query, $params)) {
                $message = $action === 'add' ? 'Fournisseur ajouté avec succès.' : 'Fournisseur modifié avec succès.';
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
            if ($db->execute("UPDATE fournisseurs SET actif = 0 WHERE id = :id", [':id' => $id])) {
                $message = 'Fournisseur supprimé avec succès.';
                $messageType = 'success';
            } else {
                $message = 'Erreur lors de la suppression.';
                $messageType = 'danger';
            }
        }
    }
}

// Récupérer les fournisseurs
$fournisseurs = $db->select("SELECT f.*, 
           COUNT(m.id) as nb_medicaments
    FROM fournisseurs f
    LEFT JOIN medicaments m ON f.id = m.fournisseur_id AND m.actif = 1
    WHERE f.actif = 1
    GROUP BY f.id
    ORDER BY f.nom ASC
");
?>

<div class="page-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">
                    <i class="bi bi-truck me-2"></i>
                    Gestion des Fournisseurs (Magasin)
                </h1>
                <p class="page-subtitle">Consultez et gérez vos partenaires fournisseurs</p>
            </div>
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#fournisseurModal">
                <i class="bi bi-plus-lg me-2"></i>
                Nouveau Fournisseur
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

<!-- Recherche -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="search-box">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="form-control search-input" placeholder="Rechercher un fournisseur..." data-target="#fournisseursTable">
                </div>
            </div>
            <div class="col-md-6 text-end">
                <span class="text-muted">Total: <?= count($fournisseurs) ?> fournisseur(s)</span>
            </div>
        </div>
    </div>
</div>

<!-- Liste des fournisseurs -->
<div class="row" id="fournisseursTable">
    <?php foreach ($fournisseurs as $fournisseur): ?>
        <div class="col-lg-6 col-xl-4 mb-4 fournisseur-card" data-nom="<?= strtolower($fournisseur['nom']) ?>">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?= escape($fournisseur['nom']) ?></h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu text-light">
                            <li>
                                <button class="dropdown-item" onclick="editFournisseur(<?= htmlspecialchars(json_encode($fournisseur)) ?>)">
                                    <i class="bi bi-pencil me-2"></i>Modifier
                                </button>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce fournisseur ?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $fournisseur['id'] ?>">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-trash me-2"></i>Supprimer
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block">Contact principal</small>
                        <strong><?= $fournisseur['contact_principal'] ?: 'Non renseigné' ?></strong>
                    </div>
                    
                    <?php if ($fournisseur['email']): ?>
                        <div class="mb-2">
                            <i class="bi bi-envelope me-2 text-muted"></i>
                            <a href="mailto:<?= escape($fournisseur['email']) ?>"><?= escape($fournisseur['email']) ?></a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($fournisseur['telephone']): ?>
                        <div class="mb-2">
                            <i class="bi bi-telephone me-2 text-muted"></i>
                            <a href="tel:<?= escape($fournisseur['telephone']) ?>"><?= escape($fournisseur['telephone']) ?></a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($fournisseur['adresse']): ?>
                        <div class="mb-2">
                            <i class="bi bi-geo-alt me-2 text-muted"></i>
                            <small>
                                <?= escape($fournisseur['adresse']) ?>
                                <?php if ($fournisseur['ville']): ?>
                                    <br><?= escape($fournisseur['code_postal']) ?> <?= escape($fournisseur['ville']) ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="bi bi-capsule me-1"></i>
                            <?= $fournisseur['nb_medicaments'] ?> médicament(s)
                        </small>
                        <small class="text-muted">
                            Ajouté le <?= formatDate($fournisseur['date_creation']) ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal pour ajouter/modifier un fournisseur -->
<div class="modal fade" id="fournisseurModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nouveau Fournisseur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="modalAction" value="add">
                    <input type="hidden" name="id" id="modalId">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="nom" class="form-label">Nom du fournisseur *</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                        <div class="col-md-4">
                            <label for="contact_principal" class="form-label">Contact principal</label>
                            <input type="text" class="form-control" id="contact_principal" name="contact_principal">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-6">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone">
                        </div>
                        
                        <div class="col-12">
                            <label for="adresse" class="form-label">Adresse</label>
                            <textarea class="form-control" id="adresse" name="adresse" rows="2"></textarea>
                        </div>
                        
                        <div class="col-md-8">
                            <label for="ville" class="form-label">Ville</label>
                            <input type="text" class="form-control" id="ville" name="ville">
                        </div>
                        <div class="col-md-4">
                            <label for="code_postal" class="form-label">Code postal</label>
                            <input type="text" class="form-control" id="code_postal" name="code_postal">
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
function editFournisseur(fournisseur) {
    document.getElementById('modalTitle').textContent = 'Modifier le fournisseur';
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('modalId').value = fournisseur.id;
    
    document.getElementById('nom').value = fournisseur.nom || '';
    document.getElementById('email').value = fournisseur.email || '';
    document.getElementById('telephone').value = fournisseur.telephone || '';
    document.getElementById('adresse').value = fournisseur.adresse || '';
    document.getElementById('ville').value = fournisseur.ville || '';
    document.getElementById('code_postal').value = fournisseur.code_postal || '';
    document.getElementById('contact_principal').value = fournisseur.contact_principal || '';
    
    new bootstrap.Modal(document.getElementById('fournisseurModal')).show();
}

function resetModal() {
    document.getElementById('modalTitle').textContent = 'Nouveau Fournisseur';
    document.getElementById('modalAction').value = 'add';
    document.getElementById('modalId').value = '';
    document.querySelector('#fournisseurModal form').reset();
    document.querySelector('#fournisseurModal form').classList.remove('was-validated');
}

document.getElementById('fournisseurModal').addEventListener('hidden.bs.modal', resetModal);

// Filtrage en direct
document.querySelector('.search-input').addEventListener('input', function() {
    const search = this.value.toLowerCase();
    const cards = document.querySelectorAll('.fournisseur-card');
    cards.forEach(card => {
        card.style.display = card.dataset.nom.includes(search) ? '' : 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
