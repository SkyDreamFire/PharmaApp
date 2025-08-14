<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier l'authentification
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

// Vérifier les permissions (seul le directeur peut gérer les utilisateurs)
if (!checkRole('directeur')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permissions insuffisantes']);
    exit();
}

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $action);
            break;
        case 'POST':
            handlePost($db, $action);
            break;
        case 'PUT':
            handlePut($db, $action);
            break;
        case 'DELETE':
            handleDelete($db, $action);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'profile':
            $id = intval($_GET['id'] ?? $_SESSION['user_id']);
            
            $user = $db->select("
                SELECT u.*, 
                       COUNT(v.id) as nb_ventes,
                       COALESCE(SUM(v.montant_total), 0) as ca_total
                FROM users u
                LEFT JOIN ventes v ON u.id = v.user_id
                WHERE u.id = :id
                GROUP BY u.id
            ", [':id' => $id])[0] ?? null;
            
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
                return;
            }
            
            // Ne pas retourner le mot de passe
            unset($user['password']);
            
            echo json_encode(['success' => true, 'data' => $user]);
            break;
            
        case 'stats':
            $stats = [
                'total_users' => $db->select("SELECT COUNT(*) as count FROM users WHERE actif = 1")[0]['count'],
                'directeurs' => $db->select("SELECT COUNT(*) as count FROM users WHERE role = 'directeur' AND actif = 1")[0]['count'],
                'caissiers' => $db->select("SELECT COUNT(*) as count FROM users WHERE role = 'caissier' AND actif = 1")[0]['count'],
                'inactifs' => $db->select("SELECT COUNT(*) as count FROM users WHERE actif = 0")[0]['count']
            ];
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'activity':
            $user_id = intval($_GET['user_id'] ?? 0);
            $limit = intval($_GET['limit'] ?? 10);
            
            $where = $user_id > 0 ? "WHERE v.user_id = :user_id" : "";
            $params = $user_id > 0 ? [':user_id' => $user_id, ':limit' => $limit] : [':limit' => $limit];
            
            $activites = $db->select("
                SELECT v.date_vente, v.numero_facture, v.montant_total,
                       c.nom as client_nom, c.prenom as client_prenom,
                       u.nom as user_nom, u.prenom as user_prenom
                FROM ventes v
                LEFT JOIN clients c ON v.client_id = c.id
                LEFT JOIN users u ON v.user_id = u.id
                $where
                ORDER BY v.date_vente DESC
                LIMIT :limit
            ", $params);
            
            echo json_encode(['success' => true, 'data' => $activites]);
            break;
            
        default:
            $users = $db->select("
                SELECT u.id, u.username, u.email, u.role, u.nom, u.prenom, 
                       u.telephone, u.date_creation, u.actif,
                       COUNT(v.id) as nb_ventes,
                       COALESCE(SUM(v.montant_total), 0) as ca_total
                FROM users u
                LEFT JOIN ventes v ON u.id = v.user_id
                GROUP BY u.id
                ORDER BY u.nom ASC, u.prenom ASC
            ");
            
            echo json_encode(['success' => true, 'data' => $users]);
    }
}

function handlePost($db, $action) {
    switch ($action) {
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['username', 'email', 'password', 'nom', 'prenom'];
            $errors = validateRequired($required, $data);
            if (!empty($errors)) {
                echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
                return;
            }
            
            if (!validateEmail($data['email'])) {
                echo json_encode(['success' => false, 'message' => 'Format d\'email invalide']);
                return;
            }
            
            // Vérifier l'unicité du nom d'utilisateur et de l'email
            $existing = $db->select("SELECT id FROM users WHERE username = :username OR email = :email", [
                ':username' => $data['username'],
                ':email' => $data['email']
            ]);
            
            if (!empty($existing)) {
                echo json_encode(['success' => false, 'message' => 'Nom d\'utilisateur ou email déjà utilisé']);
                return;
            }
            
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (username, email, password, role, nom, prenom, telephone) 
                     VALUES (:username, :email, :password, :role, :nom, :prenom, :telephone)";
            
            $params = [
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':password' => $hashedPassword,
                ':role' => $data['role'] ?? 'caissier',
                ':nom' => $data['nom'],
                ':prenom' => $data['prenom'],
                ':telephone' => $data['telephone'] ?? null
            ];
            
            if ($db->execute($query, $params)) {
                echo json_encode(['success' => true, 'message' => 'Utilisateur créé avec succès', 'id' => $db->lastInsertId()]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la création']);
            }
            break;
            
        case 'change_password':
            $data = json_decode(file_get_contents('php://input'), true);
            $user_id = intval($data['user_id'] ?? $_SESSION['user_id']);
            $current_password = $data['current_password'] ?? '';
            $new_password = $data['new_password'] ?? '';
            
            if (empty($current_password) || empty($new_password)) {
                echo json_encode(['success' => false, 'message' => 'Mots de passe requis']);
                return;
            }
            
            // Vérifier le mot de passe actuel
            $user = $db->select("SELECT password FROM users WHERE id = :id", [':id' => $user_id])[0] ?? null;
            if (!$user || !password_verify($current_password, $user['password'])) {
                echo json_encode(['success' => false, 'message' => 'Mot de passe actuel incorrect']);
                return;
            }
            
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            
            if ($db->execute("UPDATE users SET password = :password WHERE id = :id", [
                ':password' => $hashedPassword,
                ':id' => $user_id
            ])) {
                echo json_encode(['success' => true, 'message' => 'Mot de passe modifié avec succès']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
}

function handlePut($db, $action) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        return;
    }
    
    // Empêcher la modification de son propre compte via cette API
    if ($id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Utilisez le profil pour modifier votre compte']);
        return;
    }
    
    $query = "UPDATE users SET username = :username, email = :email, role = :role,
             nom = :nom, prenom = :prenom, telephone = :telephone";
    
    $params = [
        ':id' => $id,
        ':username' => $data['username'],
        ':email' => $data['email'],
        ':role' => $data['role'],
        ':nom' => $data['nom'],
        ':prenom' => $data['prenom'],
        ':telephone' => $data['telephone'] ?? null
    ];
    
    // Ajouter le mot de passe si fourni
    if (!empty($data['password'])) {
        $query .= ", password = :password";
        $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    
    $query .= " WHERE id = :id";
    
    if ($db->execute($query, $params)) {
        echo json_encode(['success' => true, 'message' => 'Utilisateur mis à jour avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
    }
}

function handleDelete($db, $action) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        return;
    }
    
    // Empêcher la suppression de son propre compte
    if ($id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Impossible de supprimer votre propre compte']);
        return;
    }
    
    if ($db->execute("UPDATE users SET actif = 0 WHERE id = :id", [':id' => $id])) {
        echo json_encode(['success' => true, 'message' => 'Utilisateur désactivé avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la désactivation']);
    }
}
?>