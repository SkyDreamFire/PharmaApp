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
        case 'details':
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID invalide']);
                return;
            }
            
            // Récupérer les détails de la vente
            $vente = $db->select("
                SELECT v.*, c.nom as client_nom, c.prenom as client_prenom, c.telephone as client_tel,
                       u.nom as vendeur_nom, u.prenom as vendeur_prenom
                FROM ventes v
                LEFT JOIN clients c ON v.client_id = c.id
                LEFT JOIN users u ON v.user_id = u.id
                WHERE v.id = :id
            ", [':id' => $id])[0] ?? null;
            
            if (!$vente) {
                echo json_encode(['success' => false, 'message' => 'Vente introuvable']);
                return;
            }
            
            // Récupérer les détails des articles
            $details = $db->select("
                SELECT vd.*, m.nom as medicament_nom, m.unite
                FROM vente_details vd
                JOIN medicaments m ON vd.medicament_id = m.id
                WHERE vd.vente_id = :vente_id
                ORDER BY m.nom ASC
            ", [':vente_id' => $id]);
            
            $html = generateDetailsHtml($vente, $details);
            echo json_encode(['success' => true, 'html' => $html]);
            break;
            
        case 'client_history':
            $client_id = intval($_GET['client_id'] ?? 0);
            if ($client_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID client invalide']);
                return;
            }
            
            $ventes = $db->select("
                SELECT v.*, u.nom as vendeur_nom, u.prenom as vendeur_prenom,
                       COUNT(vd.id) as nb_articles
                FROM ventes v
                LEFT JOIN users u ON v.user_id = u.id
                LEFT JOIN vente_details vd ON v.id = vd.vente_id
                WHERE v.client_id = :client_id
                GROUP BY v.id
                ORDER BY v.date_vente DESC
                LIMIT 20
            ", [':client_id' => $client_id]);
            
            $html = generateClientHistoryHtml($ventes);
            echo json_encode(['success' => true, 'html' => $html]);
            break;
            
        case 'print':
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID invalide']);
                return;
            }
            
            printFacture($db, $id);
            break;
            
        case 'stats':
            $periode = $_GET['periode'] ?? 'today';
            $stats = getVentesStats($db, $periode);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
}

function handlePost($db, $action) {
    switch ($action) {
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $client_id = intval($data['client_id'] ?? 0) ?: null;
            $mode_paiement = $data['mode_paiement'] ?? 'especes';
            $remise = floatval($data['remise'] ?? 0);
            $notes = trim($data['notes'] ?? '');
            $articles = $data['articles'] ?? [];
            
            if (empty($articles)) {
                echo json_encode(['success' => false, 'message' => 'Aucun article sélectionné']);
                return;
            }
            
            $db->beginTransaction();
            try {
                // Calculer le montant total
                $montant_total = 0;
                foreach ($articles as $article) {
                    $montant_total += $article['prix'] * $article['quantite'];
                }
                $montant_total -= $remise;
                
                // Générer le numéro de facture
                $numero_facture = generateInvoiceNumber();
                
                // Créer la vente
                $vente_id = $db->execute("INSERT INTO ventes (numero_facture, client_id, user_id, montant_total, remise, mode_paiement, notes) 
                                         VALUES (:numero_facture, :client_id, :user_id, :montant_total, :remise, :mode_paiement, :notes)", [
                    ':numero_facture' => $numero_facture,
                    ':client_id' => $client_id,
                    ':user_id' => $_SESSION['user_id'],
                    ':montant_total' => $montant_total,
                    ':remise' => $remise,
                    ':mode_paiement' => $mode_paiement,
                    ':notes' => $notes
                ]);
                
                if ($vente_id) {
                    $vente_id = $db->lastInsertId();
                    
                    // Ajouter les détails et mettre à jour le stock
                    foreach ($articles as $article) {
                        // Ajouter le détail
                        $db->execute("INSERT INTO vente_details (vente_id, medicament_id, quantite, prix_unitaire, sous_total) 
                                     VALUES (:vente_id, :medicament_id, :quantite, :prix_unitaire, :sous_total)", [
                            ':vente_id' => $vente_id,
                            ':medicament_id' => $article['id'],
                            ':quantite' => $article['quantite'],
                            ':prix_unitaire' => $article['prix'],
                            ':sous_total' => $article['prix'] * $article['quantite']
                        ]);
                        
                        // Mettre à jour le stock
                        $db->execute("UPDATE medicaments SET stock_actuel = stock_actuel - :quantite WHERE id = :id", [
                            ':quantite' => $article['quantite'],
                            ':id' => $article['id']
                        ]);
                        
                        // Enregistrer le mouvement de stock
                        $db->execute("INSERT INTO stock_mouvements (medicament_id, type_mouvement, quantite, quantite_avant, quantite_apres, user_id, motif) 
                                     SELECT :medicament_id, 'sortie', :quantite, stock_actuel + :quantite, stock_actuel, :user_id, :motif 
                                     FROM medicaments WHERE id = :medicament_id", [
                            ':medicament_id' => $article['id'],
                            ':quantite' => $article['quantite'],
                            ':user_id' => $_SESSION['user_id'],
                            ':motif' => 'Vente - Facture ' . $numero_facture
                        ]);
                    }
                    
                    $db->commit();
                    echo json_encode(['success' => true, 'message' => 'Vente créée avec succès', 'vente_id' => $vente_id, 'numero_facture' => $numero_facture]);
                } else {
                    throw new Exception('Erreur lors de la création de la vente');
                }
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
}

function generateDetailsHtml($vente, $details) {
    $html = '<div class="row">';
    $html .= '<div class="col-md-6">';
    $html .= '<h6>Informations de la vente</h6>';
    $html .= '<table class="table table-sm">';
    $html .= '<tr><td><strong>N° Facture:</strong></td><td>' . escape($vente['numero_facture']) . '</td></tr>';
    $html .= '<tr><td><strong>Date:</strong></td><td>' . formatDateTime($vente['date_vente']) . '</td></tr>';
    $html .= '<tr><td><strong>Vendeur:</strong></td><td>' . escape($vente['vendeur_nom'] . ' ' . $vente['vendeur_prenom']) . '</td></tr>';
    
    if ($vente['client_nom']) {
        $html .= '<tr><td><strong>Client:</strong></td><td>' . escape($vente['client_nom'] . ' ' . $vente['client_prenom']) . '</td></tr>';
        if ($vente['client_tel']) {
            $html .= '<tr><td><strong>Téléphone:</strong></td><td>' . escape($vente['client_tel']) . '</td></tr>';
        }
    }
    
    $html .= '<tr><td><strong>Mode de paiement:</strong></td><td>' . ucfirst($vente['mode_paiement']) . '</td></tr>';
    if ($vente['remise'] > 0) {
        $html .= '<tr><td><strong>Remise:</strong></td><td>' . formatPrice($vente['remise']) . '</td></tr>';
    }
    $html .= '<tr><td><strong>Total:</strong></td><td class="text-success"><strong>' . formatPrice($vente['montant_total']) . '</strong></td></tr>';
    $html .= '</table>';
    $html .= '</div>';
    
    $html .= '<div class="col-md-6">';
    $html .= '<h6>Articles vendus</h6>';
    $html .= '<table class="table table-sm">';
    $html .= '<thead><tr><th>Article</th><th>Qté</th><th>Prix</th><th>Total</th></tr></thead>';
    $html .= '<tbody>';
    
    foreach ($details as $detail) {
        $html .= '<tr>';
        $html .= '<td>' . escape($detail['medicament_nom']) . '</td>';
        $html .= '<td>' . $detail['quantite'] . ' ' . escape($detail['unite']) . '</td>';
        $html .= '<td>' . formatPrice($detail['prix_unitaire']) . '</td>';
        $html .= '<td>' . formatPrice($detail['sous_total']) . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    $html .= '</div>';
    $html .= '</div>';
    
    if ($vente['notes']) {
        $html .= '<div class="mt-3"><h6>Notes</h6><p>' . escape($vente['notes']) . '</p></div>';
    }
    
    return $html;
}

function generateClientHistoryHtml($ventes) {
    if (empty($ventes)) {
        return '<div class="text-center py-4"><p class="text-muted">Aucun achat trouvé</p></div>';
    }
    
    $html = '<div class="table-responsive">';
    $html .= '<table class="table table-hover">';
    $html .= '<thead><tr><th>N° Facture</th><th>Date</th><th>Vendeur</th><th>Articles</th><th>Montant</th><th>Paiement</th></tr></thead>';
    $html .= '<tbody>';
    
    foreach ($ventes as $vente) {
        $html .= '<tr>';
        $html .= '<td><strong>' . escape($vente['numero_facture']) . '</strong></td>';
        $html .= '<td>' . formatDateTime($vente['date_vente']) . '</td>';
        $html .= '<td>' . escape($vente['vendeur_nom'] . ' ' . $vente['vendeur_prenom']) . '</td>';
        $html .= '<td><span class="badge bg-secondary">' . $vente['nb_articles'] . '</span></td>';
        $html .= '<td><strong class="text-success">' . formatPrice($vente['montant_total']) . '</strong></td>';
        $html .= '<td><span class="badge bg-primary">' . ucfirst($vente['mode_paiement']) . '</span></td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    $html .= '</div>';
    
    return $html;
}

function printFacture($db, $id) {
    // Récupérer les données de la facture
    $vente = $db->select("
        SELECT v.*, c.nom as client_nom, c.prenom as client_prenom, c.adresse as client_adresse,
               c.telephone as client_tel, c.email as client_email
        FROM ventes v
        LEFT JOIN clients c ON v.client_id = c.id
        WHERE v.id = :id
    ", [':id' => $id])[0] ?? null;
    
    if (!$vente) {
        echo "Facture introuvable";
        return;
    }
    
    $details = $db->select("
        SELECT vd.*, m.nom as medicament_nom, m.unite
        FROM vente_details vd
        JOIN medicaments m ON vd.medicament_id = m.id
        WHERE vd.vente_id = :vente_id
        ORDER BY m.nom ASC
    ", [':vente_id' => $id]);
    
    // Générer le HTML de la facture
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Facture <?= escape($vente['numero_facture']) ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .info { display: flex; justify-content: space-between; margin-bottom: 30px; }
            .info div { width: 45%; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .total { text-align: right; font-size: 18px; font-weight: bold; }
            .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            @media print { body { margin: 0; } }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>FIANGEP PHARMA</h1>
            <h2>FACTURE</h2>
            <p>N° <?= escape($vente['numero_facture']) ?></p>
        </div>
        
        <div class="info">
            <div>
                <h3>Pharmacie</h3>
                <p>Quartier Administratif<br>Dschang, Ouest<br>Tél: +237 6 53 36 83 11</p>
            </div>
            <div>
                <?php if ($vente['client_nom']): ?>
                    <h3>Client</h3>
                    <p>
                        <?= escape($vente['client_nom'] . ' ' . $vente['client_prenom']) ?><br>
                        <?php if ($vente['client_adresse']): ?>
                            <?= nl2br(escape($vente['client_adresse'])) ?><br>
                        <?php endif; ?>
                        <?php if ($vente['client_tel']): ?>
                            Tél: <?= escape($vente['client_tel']) ?><br>
                        <?php endif; ?>
                        <?php if ($vente['client_email']): ?>
                            Email: <?= escape($vente['client_email']) ?>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <h3>Vente directe</h3>
                <?php endif; ?>
                <p><strong>Date:</strong> <?= formatDateTime($vente['date_vente']) ?></p>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th>Quantité</th>
                    <th>Prix unitaire</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($details as $detail): ?>
                    <tr>
                        <td><?= escape($detail['medicament_nom']) ?></td>
                        <td><?= $detail['quantite'] ?> <?= escape($detail['unite']) ?></td>
                        <td><?= formatPrice($detail['prix_unitaire']) ?></td>
                        <td><?= formatPrice($detail['sous_total']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="total">
            <?php if ($vente['remise'] > 0): ?>
                <p>Sous-total: <?= formatPrice($vente['montant_total'] + $vente['remise']) ?></p>
                <p>Remise: -<?= formatPrice($vente['remise']) ?></p>
            <?php endif; ?>
            <p>TOTAL: <?= formatPrice($vente['montant_total']) ?></p>
            <p>Mode de paiement: <?= ucfirst($vente['mode_paiement']) ?></p>
        </div>
        
        <?php if ($vente['notes']): ?>
            <div>
                <h4>Notes:</h4>
                <p><?= nl2br(escape($vente['notes'])) ?></p>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Merci de votre visite !</p>
        </div>
        
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>
    <?php
}

function getVentesStats($db, $periode) {
    $where = '';
    switch ($periode) {
        case 'today':
            $where = "WHERE DATE(date_vente) = CURDATE()";
            break;
        case 'week':
            $where = "WHERE date_vente >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where = "WHERE MONTH(date_vente) = MONTH(CURDATE()) AND YEAR(date_vente) = YEAR(CURDATE())";
            break;
        case 'year':
            $where = "WHERE YEAR(date_vente) = YEAR(CURDATE())";
            break;
    }
    
    $stats = $db->select("
        SELECT 
            COUNT(*) as nb_ventes,
            COALESCE(SUM(montant_total), 0) as ca_total,
            COALESCE(AVG(montant_total), 0) as panier_moyen
        FROM ventes $where
    ")[0];
    
    return $stats;
}
?>