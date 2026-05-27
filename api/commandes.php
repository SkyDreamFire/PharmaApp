<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier l'authentification et les droits
if (!isLoggedIn() || (!checkRole('magasinier') && !checkRole('directeur'))) {
    http_response_code(401);
    echo "Non autorisé";
    exit();
}

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

if ($action === 'print') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo "ID invalide";
        exit();
    }
    
    // Récupérer les données de la commande
    $commande = $db->select("
        SELECT c.*, f.nom as fournisseur_nom, f.adresse as fournisseur_adresse,
               f.telephone as fournisseur_tel, f.email as fournisseur_email,
               u.nom as auteur_nom, u.prenom as auteur_prenom
        FROM commandes_fournisseurs c
        LEFT JOIN fournisseurs f ON c.fournisseur_id = f.id
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.id = :id
    ", [':id' => $id])[0] ?? null;
    
    if (!$commande) {
        echo "Commande introuvable";
        exit();
    }
    
    // Récupérer les détails
    $details = $db->select("
        SELECT d.*, m.nom as medicament_nom, m.unite
        FROM details_commande_fournisseurs d
        JOIN medicaments m ON d.medicament_id = m.id
        WHERE d.commande_id = :commande_id
        ORDER BY m.nom ASC
    ", [':commande_id' => $id]);
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Bon de commande <?= escape($commande['numero_commande']) ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; font-size: 14px; }
            .header { text-align: center; margin-bottom: 30px; }
            .info { display: flex; justify-content: space-between; margin-bottom: 30px; border-top: 2px solid #333; border-bottom: 2px solid #333; padding: 15px 0; }
            .info div { width: 45%; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .total { text-align: right; font-size: 18px; font-weight: bold; margin-top: 20px; }
            .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }
            .signatures { display: flex; justify-content: space-between; margin-top: 50px; }
            .signatures div { width: 40%; text-align: center; border-top: 1px dashed #333; padding-top: 10px; }
            .status-badge { display: inline-block; padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; text-transform: uppercase; }
            .status-en_attente { background-color: #ffc107; color: #000; }
            .status-confirmee { background-color: #17a2b8; color: #fff; }
            .status-livree { background-color: #28a745; color: #fff; }
            .status-annulee { background-color: #dc3545; color: #fff; }
            @media print { body { margin: 0; } }
        </style>
    </head>
    <body>
        <div class="header">
            <!-- Nom de la pharmacie demandé par l'utilisateur -->
            <h1>PHARMACIE MANAGEMENT</h1>
            <h2>BON DE COMMANDE FOURNISSEUR</h2>
            <p><strong>N° <?= escape($commande['numero_commande']) ?></strong></p>
            <p>
                Statut : 
                <span class="status-badge status-<?= escape($commande['statut']) ?>">
                    <?= ucfirst(str_replace('_', ' ', $commande['statut'])) ?>
                </span>
            </p>
        </div>
        
        <div class="info">
            <div>
                <h3>Émetteur (Pharmacie)</h3>
                <p>
                    <strong>PHARMACIE MANAGEMENT</strong><br>
                    123 Rue de la Santé<br>
                    75000 Paris<br>
                    Tél: 01 23 45 67 89<br><br>
                    <strong>Généré par:</strong> <?= escape($commande['auteur_nom'] . ' ' . $commande['auteur_prenom']) ?><br>
                    <strong>Date de commande:</strong> <?= formatDateTime($commande['date_commande']) ?>
                </p>
            </div>
            <div>
                <h3>Destinataire (Fournisseur)</h3>
                <p>
                    <strong><?= escape($commande['fournisseur_nom']) ?></strong><br>
                    <?php if ($commande['fournisseur_adresse']): ?>
                        <?= nl2br(escape($commande['fournisseur_adresse'])) ?><br>
                    <?php endif; ?>
                    <?php if ($commande['fournisseur_tel']): ?>
                        Tél: <?= escape($commande['fournisseur_tel']) ?><br>
                    <?php endif; ?>
                    <?php if ($commande['fournisseur_email']): ?>
                        Email: <?= escape($commande['fournisseur_email']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Désignation de l'article</th>
                    <th>Quantité demandée</th>
                    <th>Prix unitaire estimé</th>
                    <th>Sous-total</th>
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
            <p>MONTANT TOTAL ESTIMÉ : <?= formatPrice($commande['montant_total']) ?></p>
        </div>
        
        <?php if ($commande['notes']): ?>
            <div style="margin-top: 30px;">
                <h4>Notes et Instructions :</h4>
                <div style="padding: 10px; border: 1px solid #ddd; background-color: #f9f9f9;">
                    <?= nl2br(escape($commande['notes'])) ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="signatures">
            <div>
                <strong>Le Fournisseur</strong><br>
                <small>(Signature et Cachet)</small>
            </div>
            <div>
                <strong>Le Responsable Pharmacie</strong><br>
                <small>(Signature et Cachet)</small>
            </div>
        </div>
        
        <div class="footer">
            <p>Document généré par le système de gestion de Pharmacie Management</p>
        </div>
        
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>
    <?php
} else {
    echo "Action non reconnue";
}
?>
