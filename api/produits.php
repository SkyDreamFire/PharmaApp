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
        case 'search':
            $query = $_GET['q'] ?? '';
            $limit = intval($_GET['limit'] ?? 10);
            
            $medicaments = $db->select("
                SELECT m.*, c.nom as categorie_nom
                FROM medicaments m
                LEFT JOIN categories c ON m.categorie_id = c.id
                WHERE m.actif = 1 AND (m.nom LIKE :query OR m.code_barre LIKE :query)
                ORDER BY m.nom ASC
                LIMIT :limit
            ", [
                ':query' => '%' . $query . '%',
                ':limit' => $limit
            ]);
            
            echo json_encode(['success' => true, 'data' => $medicaments]);
            break;
            
        case 'details':
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID invalide']);
                return;
            }
            
            $medicament = $db->select("
                SELECT m.*, c.nom as categorie_nom, f.nom as fournisseur_nom
                FROM medicaments m
                LEFT JOIN categories c ON m.categorie_id = c.id
                LEFT JOIN fournisseurs f ON m.fournisseur_id = f.id
                WHERE m.id = :id AND m.actif = 1
            ", [':id' => $id]);
            
            if (empty($medicament)) {
                echo json_encode(['success' => false, 'message' => 'Médicament introuvable']);
                return;
            }
            
            echo json_encode(['success' => true, 'data' => $medicament[0]]);
            break;
            
        case 'stock_alerts':
            $alertes = $db->select("
                SELECT m.*, c.nom as categorie_nom, f.nom as fournisseur_nom
                FROM medicaments m
                LEFT JOIN categories c ON m.categorie_id = c.id
                LEFT JOIN fournisseurs f ON m.fournisseur_id = f.id
                WHERE m.stock_actuel <= m.stock_minimum AND m.actif = 1
                ORDER BY (m.stock_actuel / NULLIF(m.stock_minimum, 0)) ASC
            ");
            
            echo json_encode(['success' => true, 'data' => $alertes]);
            break;
            
        default:
            $medicaments = $db->select("
                SELECT m.*, c.nom as categorie_nom, f.nom as fournisseur_nom
                FROM medicaments m
                LEFT JOIN categories c ON m.categorie_id = c.id
                LEFT JOIN fournisseurs f ON m.fournisseur_id = f.id
                WHERE m.actif = 1
                ORDER BY m.nom ASC
            ");
            
            echo json_encode(['success' => true, 'data' => $medicaments]);
    }
}

function handlePost($db, $action) {
    // Vérifier les permissions pour les actions de modification
    if (!checkRole('directeur') && $action !== 'update_stock') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permissions insuffisantes']);
        return;
    }
    
    switch ($action) {
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['nom', 'prix_achat', 'prix_vente'];
            $errors = validateRequired($required, $data);
            if (!empty($errors)) {
                echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
                return;
            }
            
            $query = "INSERT INTO medicaments (nom, code_barre, description, prix_achat, prix_vente, 
                     stock_actuel, stock_minimum, unite, date_expiration, categorie_id, fournisseur_id) 
                     VALUES (:nom, :code_barre, :description, :prix_achat, :prix_vente, 
                     :stock_actuel, :stock_minimum, :unite, :date_expiration, :categorie_id, :fournisseur_id)";
            
            $params = [
                ':nom' => $data['nom'],
                ':code_barre' => $data['code_barre'] ?? null,
                ':description' => $data['description'] ?? null,
                ':prix_achat' => floatval($data['prix_achat']),
                ':prix_vente' => floatval($data['prix_vente']),
                ':stock_actuel' => intval($data['stock_actuel'] ?? 0),
                ':stock_minimum' => intval($data['stock_minimum'] ?? 10),
                ':unite' => $data['unite'] ?? 'unité',
                ':date_expiration' => $data['date_expiration'] ?? null,
                ':categorie_id' => intval($data['categorie_id']) ?: null,
                ':fournisseur_id' => intval($data['fournisseur_id']) ?: null
            ];
            
            if ($db->execute($query, $params)) {
                echo json_encode(['success' => true, 'message' => 'Médicament créé avec succès', 'id' => $db->lastInsertId()]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la création']);
            }
            break;
            
        case 'update_stock':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = intval($data['id'] ?? 0);
            $nouveau_stock = intval($data['stock'] ?? 0);
            $motif = $data['motif'] ?? 'Ajustement via API';
            
            if ($id <= 0 || $nouveau_stock < 0) {
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                return;
            }
            
            // Récupérer le stock actuel
            $medicament = $db->select("SELECT stock_actuel, nom FROM medicaments WHERE id = :id", [':id' => $id])[0] ?? null;
            if (!$medicament) {
                echo json_encode(['success' => false, 'message' => 'Médicament introuvable']);
                return;
            }
            
            $stock_avant = $medicament['stock_actuel'];
            
            $db->beginTransaction();
            try {
                // Mettre à jour le stock
                $db->execute("UPDATE medicaments SET stock_actuel = :stock WHERE id = :id", [
                    ':stock' => $nouveau_stock,
                    ':id' => $id
                ]);
                
                // Enregistrer le mouvement
                $db->execute("INSERT INTO stock_mouvements (medicament_id, type_mouvement, quantite, quantite_avant, quantite_apres, user_id, motif) 
                             VALUES (:medicament_id, 'ajustement', :quantite, :quantite_avant, :quantite_apres, :user_id, :motif)", [
                    ':medicament_id' => $id,
                    ':quantite' => abs($nouveau_stock - $stock_avant),
                    ':quantite_avant' => $stock_avant,
                    ':quantite_apres' => $nouveau_stock,
                    ':user_id' => $_SESSION['user_id'],
                    ':motif' => $motif
                ]);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Stock mis à jour avec succès']);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
}

function handlePut($db, $action) {
    if (!checkRole('directeur')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permissions insuffisantes']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        return;
    }
    
    $query = "UPDATE medicaments SET nom = :nom, code_barre = :code_barre, description = :description,
             prix_achat = :prix_achat, prix_vente = :prix_vente, stock_actuel = :stock_actuel,
             stock_minimum = :stock_minimum, unite = :unite, date_expiration = :date_expiration,
             categorie_id = :categorie_id, fournisseur_id = :fournisseur_id 
             WHERE id = :id";
    
    $params = [
        ':id' => $id,
        ':nom' => $data['nom'],
        ':code_barre' => $data['code_barre'] ?? null,
        ':description' => $data['description'] ?? null,
        ':prix_achat' => floatval($data['prix_achat']),
        ':prix_vente' => floatval($data['prix_vente']),
        ':stock_actuel' => intval($data['stock_actuel']),
        ':stock_minimum' => intval($data['stock_minimum']),
        ':unite' => $data['unite'],
        ':date_expiration' => $data['date_expiration'] ?? null,
        ':categorie_id' => intval($data['categorie_id']) ?: null,
        ':fournisseur_id' => intval($data['fournisseur_id']) ?: null
    ];
    
    if ($db->execute($query, $params)) {
        echo json_encode(['success' => true, 'message' => 'Médicament mis à jour avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
    }
}

function handleDelete($db, $action) {
    if (!checkRole('directeur')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permissions insuffisantes']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        return;
    }
    
    if ($db->execute("UPDATE medicaments SET actif = 0 WHERE id = :id", [':id' => $id])) {
        echo json_encode(['success' => true, 'message' => 'Médicament supprimé avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
    }
}
?>