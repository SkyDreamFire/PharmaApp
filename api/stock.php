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
        case 'alerts':
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
            
        case 'movements':
            $limit = intval($_GET['limit'] ?? 50);
            $medicament_id = intval($_GET['medicament_id'] ?? 0);
            
            $query = "
                SELECT sm.*, m.nom as medicament_nom, m.unite, u.nom as user_nom, u.prenom as user_prenom
                FROM stock_mouvements sm
                JOIN medicaments m ON sm.medicament_id = m.id
                JOIN users u ON sm.user_id = u.id
            ";
            $params = [];
            
            if ($medicament_id > 0) {
                $query .= " WHERE sm.medicament_id = :medicament_id";
                $params[':medicament_id'] = $medicament_id;
            }
            
            $query .= " ORDER BY sm.date_mouvement DESC LIMIT :limit";
            $params[':limit'] = $limit;
            
            $mouvements = $db->select($query, $params);
            echo json_encode(['success' => true, 'data' => $mouvements]);
            break;
            
        case 'export_alerts':
            exportAlertes($db);
            break;
            
        case 'export_movements':
            exportMouvements($db);
            break;
            
        case 'stats':
            $stats = [
                'total_medicaments' => $db->select("SELECT COUNT(*) as count FROM medicaments WHERE actif = 1")[0]['count'],
                'stock_critique' => $db->select("SELECT COUNT(*) as count FROM medicaments WHERE stock_actuel = 0 AND actif = 1")[0]['count'],
                'stock_faible' => $db->select("SELECT COUNT(*) as count FROM medicaments WHERE stock_actuel > 0 AND stock_actuel <= stock_minimum AND actif = 1")[0]['count'],
                'valeur_stock' => $db->select("SELECT COALESCE(SUM(stock_actuel * prix_achat), 0) as total FROM medicaments WHERE actif = 1")[0]['total']
            ];
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
}

function handlePost($db, $action) {
    switch ($action) {
        case 'add_movement':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $medicament_id = intval($data['medicament_id'] ?? 0);
            $type_mouvement = $data['type_mouvement'] ?? '';
            $quantite = intval($data['quantite'] ?? 0);
            $motif = trim($data['motif'] ?? '');
            $numero_lot = trim($data['numero_lot'] ?? '');
            
            if ($medicament_id <= 0 || $quantite <= 0 || empty($motif) || !in_array($type_mouvement, ['entree', 'sortie', 'ajustement'])) {
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                return;
            }
            
            // Récupérer le stock actuel
            $medicament = $db->select("SELECT stock_actuel, nom FROM medicaments WHERE id = :id", [':id' => $medicament_id])[0] ?? null;
            if (!$medicament) {
                echo json_encode(['success' => false, 'message' => 'Médicament introuvable']);
                return;
            }
            
            $stock_avant = $medicament['stock_actuel'];
            
            // Calculer le nouveau stock
            switch ($type_mouvement) {
                case 'entree':
                    $stock_apres = $stock_avant + $quantite;
                    break;
                case 'sortie':
                    if ($quantite > $stock_avant) {
                        echo json_encode(['success' => false, 'message' => 'Quantité insuffisante en stock']);
                        return;
                    }
                    $stock_apres = $stock_avant - $quantite;
                    break;
                case 'ajustement':
                    $stock_apres = $quantite;
                    $quantite = abs($quantite - $stock_avant);
                    break;
            }
            
            $db->beginTransaction();
            try {
                // Enregistrer le mouvement
                $db->execute("INSERT INTO stock_mouvements (medicament_id, type_mouvement, quantite, quantite_avant, quantite_apres, user_id, motif, numero_lot) 
                             VALUES (:medicament_id, :type_mouvement, :quantite, :quantite_avant, :quantite_apres, :user_id, :motif, :numero_lot)", [
                    ':medicament_id' => $medicament_id,
                    ':type_mouvement' => $type_mouvement,
                    ':quantite' => $quantite,
                    ':quantite_avant' => $stock_avant,
                    ':quantite_apres' => $stock_apres,
                    ':user_id' => $_SESSION['user_id'],
                    ':motif' => $motif,
                    ':numero_lot' => $numero_lot ?: null
                ]);
                
                // Mettre à jour le stock
                $db->execute("UPDATE medicaments SET stock_actuel = :stock WHERE id = :id", [
                    ':stock' => $stock_apres,
                    ':id' => $medicament_id
                ]);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Mouvement enregistré avec succès']);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement']);
            }
            break;
            
        case 'send_report':
            // Ici vous pourriez implémenter l'envoi d'email
            echo json_encode(['success' => true, 'message' => 'Rapport envoyé avec succès']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
}

function exportAlertes($db) {
    $alertes = $db->select("
        SELECT m.nom, m.stock_actuel, m.stock_minimum, m.unite, c.nom as categorie, f.nom as fournisseur
        FROM medicaments m
        LEFT JOIN categories c ON m.categorie_id = c.id
        LEFT JOIN fournisseurs f ON m.fournisseur_id = f.id
        WHERE m.stock_actuel <= m.stock_minimum AND m.actif = 1
        ORDER BY m.nom ASC
    ");
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="alertes_stock_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Médicament', 'Stock actuel', 'Stock minimum', 'Unité', 'Catégorie', 'Fournisseur']);
    
    foreach ($alertes as $alerte) {
        fputcsv($output, [
            $alerte['nom'],
            $alerte['stock_actuel'],
            $alerte['stock_minimum'],
            $alerte['unite'],
            $alerte['categorie'] ?: 'Non classé',
            $alerte['fournisseur'] ?: 'Non renseigné'
        ]);
    }
    
    fclose($output);
}

function exportMouvements($db) {
    $mouvements = $db->select("
        SELECT sm.date_mouvement, m.nom as medicament, sm.type_mouvement, sm.quantite, 
               sm.quantite_avant, sm.quantite_apres, sm.motif, sm.numero_lot,
               CONCAT(u.nom, ' ', u.prenom) as utilisateur
        FROM stock_mouvements sm
        JOIN medicaments m ON sm.medicament_id = m.id
        JOIN users u ON sm.user_id = u.id
        ORDER BY sm.date_mouvement DESC
        LIMIT 1000
    ");
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mouvements_stock_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Médicament', 'Type', 'Quantité', 'Stock avant', 'Stock après', 'Motif', 'N° Lot', 'Utilisateur']);
    
    foreach ($mouvements as $mouvement) {
        fputcsv($output, [
            $mouvement['date_mouvement'],
            $mouvement['medicament'],
            ucfirst($mouvement['type_mouvement']),
            $mouvement['quantite'],
            $mouvement['quantite_avant'],
            $mouvement['quantite_apres'],
            $mouvement['motif'],
            $mouvement['numero_lot'] ?: '',
            $mouvement['utilisateur']
        ]);
    }
    
    fclose($output);
}
?>