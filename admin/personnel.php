<?php
$pageTitle = 'Gestion du Personnel';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

// Vérifier le rôle directeur
if (!checkRole('directeur')) {
    header('Location: /pharma-app/caissier/dashboard.php');
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
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'caissier';
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        
        if (empty($username) || empty($email) || empty($nom) || empty($prenom)) {
            $message = 'Veuillez remplir tous les champs obligatoires.';
            $messageType = 'danger';
        } elseif ($action === 'add' && empty($password)) {
            $message = 'Le mot de passe est obligatoire pour un nouvel utilisateur.';
            $messageType = 'danger';
        } elseif (!validateEmail($email)) {
            $message = 'Format d\'email invalide.';
            $messageType = 'danger';
        } else {
            if ($action === 'add') {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, email, password, role, nom, prenom, telephone) 
                         VALUES (:username, :email, :password, :role, :nom, :prenom, :telephone)";
                $params = [
                    ':username' => $username,
                    ':email' => $email,
                    ':password' => $hashedPassword,
                    ':role' => $role,
                    ':nom' => $nom,
                    ':prenom' => $prenom,
                    ':telephone' => $telephone ?: null
                ];
            } else {
                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $query = "UPDATE users SET username = :username, email = :email, password = :password,
                             role = :role, nom = :nom, prenom = :prenom, telephone = :telephone 
                             WHERE id = :id";
                    $params = [
                        ':username' => $username,
                        ':email' => $email,
                        ':password' => $hashedPassword,
                        ':role' => $role,
                        ':nom' => $nom,
                        ':prenom' => $prenom,
                        ':telephone' => $telephone ?: null,
                        ':id' => $id
                    ];
                } else {
                    $query = "UPDATE users SET username = :username, email = :email, role = :role,
                             nom = :nom, prenom = :prenom, telephone = :telephone 
                             WHERE id = :id";
                    $params = [
                        ':username' => $username,
                        ':email' => $email,
                        ':role' => $role,
                        ':nom' => $nom,
                        ':prenom' => $prenom,
                        ':telephone' => $telephone ?: null,
                        ':id' => $id
                    ];
                }
            }
            
            if ($db->execute($query, $params)) {
                $message = $action === 'add' ? 'Utilisateur ajouté avec succès.' : 'Utilisateur modifié avec succès.';
                $messageType = 'success';
            } else {
                $message = 'Erreur lors de l\'enregistrement. Vérifiez que le nom d\'utilisateur et l\'email sont uniques.';
                $messageType = 'danger';
            }
        }
    }
    
    if ($action === 'toggle_status') {
        $id = intval($_POST['id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);
        if ($id > 0 && $id != $_SESSION['user_id']) { // Empêcher de se désactiver soi-même
            if ($db->execute("UPDATE users SET actif = :status WHERE id = :id", [':status' => $status, ':id' => $id])) {
                $message = $status ? 'Utilisateur activé avec succès.' : 'Utilisateur désactivé avec succès.';
                $messageType = 'success';
            } else {
                $message = 'Erreur lors de la modification du statut.';
                $messageType = 'danger';
            }
        }
    }
}

// Récupérer les utilisateurs
$users = $db->select("
    SELECT u.*, 
           COUNT(v.id) as nb_ventes,
           COALESCE(SUM(v.montant_total), 0) as ca_total
    FROM users u
    LEFT JOIN ventes v ON u.id = v.user_id
    GROUP BY u.id
    ORDER BY u.nom ASC, u.prenom ASC
");
?>

<div class="page-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">
                    <i class="bi bi-people me-2"></i>
                    Gestion du Personnel
                </h1>
                <p class="page-subtitle">Gérez les utilisateurs et leurs accès</p>
            </div>
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#userModal">
                <i class="bi bi-person-plus me-2"></i>
                Nouvel Utilisateur
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
<div class="row mb-4 g-3">
    <div class="col">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="stats-icon bg-primary text-white">
                    <i class="bi bi-people"></i>
                </div>
                <h3 class="stats-number text-primary"><?= count(array_filter($users, fn($u) => $u['actif'])) ?></h3>
                <p class="stats-label">Actifs</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="stats-icon bg-success text-white">
                    <i class="bi bi-person-badge"></i>
                </div>
                <h3 class="stats-number text-success"><?= count(array_filter($users, fn($u) => $u['role'] === 'directeur')) ?></h3>
                <p class="stats-label">Directeurs</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="stats-icon bg-info text-white">
                    <i class="bi bi-shop"></i>
                </div>
                <h3 class="stats-number text-info"><?= count(array_filter($users, fn($u) => $u['role'] === 'magasinier')) ?></h3>
                <p class="stats-label">Magasiniers</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="stats-icon bg-secondary text-white">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <h3 class="stats-number text-secondary"><?= count(array_filter($users, fn($u) => $u['role'] === 'caissier')) ?></h3>
                <p class="stats-label">Caissiers</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="stats-icon bg-warning text-white">
                    <i class="bi bi-person-x"></i>
                </div>
                <h3 class="stats-number text-warning"><?= count(array_filter($users, fn($u) => !$u['actif'])) ?></h3>
                <p class="stats-label">Inactifs</p>
            </div>
        </div>
    </div>
</div>

<!-- Recherche -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="search-box">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="form-control search-input" placeholder="Rechercher un utilisateur..." data-target="#usersTable">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="roleFilter">
                    <option value="">Tous les rôles</option>
                    <option value="directeur">Directeur</option>
                    <option value="magasinier">Magasinier</option>
                    <option value="caissier">Caissier</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="statusFilter">
                    <option value="">Tous les statuts</option>
                    <option value="1">Actif</option>
                    <option value="0">Inactif</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des utilisateurs -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-list me-2"></i>
            Liste du Personnel (<?= count($users) ?>)
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table" id="usersTable">
                <thead>
                    <tr>
                        <th data-sort="nom">Utilisateur</th>
                        <th data-sort="role">Rôle</th>
                        <th data-sort="email">Contact</th>
                        <th data-sort="nb_ventes">Performances</th>
                        <th data-sort="date_creation">Création</th>
                        <th data-sort="actif">Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr data-role="<?= $user['role'] ?>" data-status="<?= $user['actif'] ?>">
                            <td data-sort="nom">
                                <div class="d-flex align-items-center">
                                    <div class="avatar me-3">
                                        <i class="bi bi-person-circle text-muted" style="font-size: 2rem;"></i>
                                    </div>
                                    <div>
                                        <strong><?= escape($user['nom'] . ' ' . $user['prenom']) ?></strong>
                                        <br><small class="text-muted">@<?= escape($user['username']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td data-sort="role">
                                <?php 
                                $badgeClass = 'bg-secondary';
                                if ($user['role'] === 'directeur') $badgeClass = 'bg-primary';
                                elseif ($user['role'] === 'magasinier') $badgeClass = 'bg-info text-dark';
                                ?>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td data-sort="email">
                                <div>
                                    <i class="bi bi-envelope me-1"></i>
                                    <a href="mailto:<?= escape($user['email']) ?>"><?= escape($user['email']) ?></a>
                                </div>
                                <?php if ($user['telephone']): ?>
                                    <div>
                                        <i class="bi bi-telephone me-1"></i>
                                        <a href="tel:<?= escape($user['telephone']) ?>"><?= escape($user['telephone']) ?></a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-sort="nb_ventes">
                                <div>
                                    <strong><?= number_format($user['nb_ventes']) ?></strong> vente(s)
                                    <br><small class="text-muted">CA: <?= formatPrice($user['ca_total']) ?></small>
                                </div>
                            </td>
                            <td data-sort="date_creation">
                                <?= formatDate($user['date_creation']) ?>
                            </td>
                            <td data-sort="actif">
                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                    <span class="badge bg-success">Actif (Vous)</span>
                                <?php else: ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $user['actif'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-sm <?= $user['actif'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                                            <?= $user['actif'] ? 'Actif' : 'Inactif' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                        <i class="bi bi-pencil"></i>
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

<!-- Modal pour ajouter/modifier un utilisateur -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nouvel Utilisateur</h5>
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
                            <label for="username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label">Rôle *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="caissier">Caissier</option>
                                <option value="magasinier">Magasinier</option>
                                <option value="directeur">Directeur</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone">
                        </div>
                        
                        <div class="col-12">
                            <label for="password" class="form-label">
                                Mot de passe <span id="passwordRequired">*</span>
                            </label>
                            <input type="password" class="form-control" id="password" name="password">
                            <div class="form-text" id="passwordHelp">
                                Laissez vide pour conserver le mot de passe actuel (modification uniquement)
                            </div>
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
function editUser(user) {
    document.getElementById('modalTitle').textContent = 'Modifier l\'utilisateur';
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('modalId').value = user.id;
    
    document.getElementById('nom').value = user.nom || '';
    document.getElementById('prenom').value = user.prenom || '';
    document.getElementById('username').value = user.username || '';
    document.getElementById('email').value = user.email || '';
    document.getElementById('telephone').value = user.telephone || '';
    document.getElementById('role').value = user.role || 'caissier';
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('passwordRequired').style.display = 'none';
    document.getElementById('passwordHelp').style.display = 'block';
    
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function resetModal() {
    document.getElementById('modalTitle').textContent = 'Nouvel Utilisateur';
    document.getElementById('modalAction').value = 'add';
    document.getElementById('modalId').value = '';
    document.querySelector('#userModal form').reset();
    document.querySelector('#userModal form').classList.remove('was-validated');
    document.getElementById('password').required = true;
    document.getElementById('passwordRequired').style.display = 'inline';
    document.getElementById('passwordHelp').style.display = 'none';
}

function filterTable() {
    const searchValue = document.querySelector('.search-input').value.toLowerCase();
    const roleValue = document.getElementById('roleFilter').value;
    const statusValue = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('#usersTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const role = row.dataset.role;
        const status = row.dataset.status;
        
        let show = true;
        
        if (searchValue && !text.includes(searchValue)) show = false;
        if (roleValue && role !== roleValue) show = false;
        if (statusValue && status !== statusValue) show = false;
        
        row.style.display = show ? '' : 'none';
    });
}

// Event listeners
document.getElementById('roleFilter').addEventListener('change', filterTable);
document.getElementById('statusFilter').addEventListener('change', filterTable);
document.getElementById('userModal').addEventListener('hidden.bs.modal', resetModal);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>