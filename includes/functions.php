<?php
/**
 * Fichier contenant les fonctions utilitaires sécurisées
 */

// Démarrage sécurisé de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie si l'utilisateur est connecté
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

/**
 * Vérifie si l'utilisateur a un rôle spécifique
 */
if (!function_exists('checkRole')) {
    function checkRole(string $requiredRole): bool {
        return isLoggedIn() && ($_SESSION['user_role'] ?? '') === $requiredRole;
    }
}

/**
 * Redirige si l'utilisateur n'est pas connecté
 */
if (!function_exists('requireLogin')) {
    function requireLogin(): void {
        if (!isLoggedIn()) {
            header('Location: /pharma-app/auth/login.php');
            exit;
        }
    }
}

/**
 * Redirige vers le tableau de bord selon le rôle
 */
if (!function_exists('redirectByRole')) {
    function redirectByRole(): void {
        if (!isLoggedIn()) {
            header('Location: /pharma-app/auth/login.php');
            exit;
        }

        switch ($_SESSION['user_role']) {
            case 'directeur':
                header('Location: /pharma-app/admin/dashboard.php');
                break;
            case 'magasinier':
                header('Location: /pharma-app/magasinier/dashboard.php');
                break;
            case 'caissier':
                header('Location: /pharma-app/caissier/dashboard.php');
                break;
            default:
                header('Location: /pharma-app/auth/login.php');
        }

        exit;
    }
}

/**
 * Protège les données en sortie (contre XSS)
 */
if (!function_exists('escape')) {
    function escape(?string $data): string {
        return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Vérifie la validité d’un email
 */
if (!function_exists('validateEmail')) {
    function validateEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

/**
 * Génère un numéro de facture unique
 */
if (!function_exists('generateInvoiceNumber')) {
    function generateInvoiceNumber(): string {
        require_once __DIR__ . '/db.php';
        $db = Database::getInstance();
        
        $today = date('Y-m-d');
        $datePrefix = date('Ymd');
        
        // Récupérer le dernier numéro de facture du jour
        $lastInvoice = $db->select("
            SELECT numero_facture 
            FROM ventes 
            WHERE DATE(date_vente) = :today 
            ORDER BY id DESC 
            LIMIT 1
        ", [':today' => $today]);
        
        if (!empty($lastInvoice)) {
            // Extraire le numéro séquentiel du dernier numéro de facture
            $lastNumber = $lastInvoice[0]['numero_facture'];
            $sequenceNumber = intval(substr($lastNumber, -4)) + 1;
        } else {
            // Premier numéro de la journée
            $sequenceNumber = 1;
        }
        
        return 'FAC' . $datePrefix . str_pad($sequenceNumber, 4, '0', STR_PAD_LEFT);
    }
}

/**
 * Génère un code-barres unique automatiquement
 */
if (!function_exists('generateBarcode')) {
    function generateBarcode(): string {
        require_once __DIR__ . '/db.php';
        $db = Database::getInstance();
        
        do {
            // Générer un code-barres de 13 chiffres (format EAN-13)
            $barcode = '340' . str_pad(rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
            
            // Vérifier l'unicité
            $existing = $db->select("SELECT id FROM medicaments WHERE code_barre = :barcode", [':barcode' => $barcode]);
        } while (!empty($existing));
        
        return $barcode;
    }
}

/**
 * Formate un prix
 */
if (!function_exists('formatPrice')) {
    function formatPrice(float $price): string {
        return number_format($price, 2, ',', ' ') . ' FCFA';
    }
}

/**
 * Formate une date simple
 */
if (!function_exists('formatDate')) {
    function formatDate(string $date): string {
        return date('d/m/Y', strtotime($date));
    }
}

/**
 * Formate une date avec l’heure
 */
if (!function_exists('formatDateTime')) {
    function formatDateTime(string $datetime): string {
        return date('d/m/Y H:i', strtotime($datetime));
    }
}

/**
 * Calcule l’âge à partir d’une date de naissance
 */
if (!function_exists('calculateAge')) {
    function calculateAge(string $birthDate): int {
        $today = new DateTime();
        $birth = new DateTime($birthDate);
        return $today->diff($birth)->y;
    }
}

/**
 * Envoie une réponse JSON avec un code HTTP
 */
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

/**
 * Valide les champs obligatoires
 */
if (!function_exists('validateRequired')) {
    function validateRequired(array $fields, array $data): array {
        $errors = [];

        foreach ($fields as $field) {
            if (empty(trim($data[$field] ?? ''))) {
                $errors[] = "Le champ « $field » est requis.";
            }
        }

        return $errors;
    }
}

/**
 * Enregistre un message d'erreur dans un fichier log
 */
if (!function_exists('logError')) {
    function logError(string $message, ?string $file = null, ?int $line = null): void {
        $logMessage = date('Y-m-d H:i:s') . " - ERREUR : $message";

        if ($file) {
            $logMessage .= " dans $file";
        }
        if ($line) {
            $logMessage .= " à la ligne $line";
        }

        error_log($logMessage . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    }
}

/**
 * Génère un token CSRF sécurisé
 */
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Vérifie la validité du token CSRF
 */
if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken(string $token): bool {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}
?>

<?php
/**
 * Fonctions pour la gestion FEFO simplifiée
 */

/**
 * Détermine quel stock utiliser en priorité selon les dates d'expiration (FEFO)
 * @param array $medicament Données du médicament avec ancien_stock, nouveau_stock et leurs dates
 * @return string 'ancien' ou 'nouveau' selon la priorité FEFO
 */
if (!function_exists('determinerStockPrioritaire')) {
    function determinerStockPrioritaire($medicament): string {
        $ancienStock = intval($medicament['ancien_stock'] ?? 0);
        $nouveauStock = intval($medicament['nouveau_stock'] ?? 0);
        $dateAncien = $medicament['date_expiration_ancien'] ?? null;
        $dateNouveau = $medicament['date_expiration_nouveau'] ?? null;
        
        // Si un des stocks est vide, utiliser l'autre
        if ($ancienStock <= 0 && $nouveauStock > 0) return 'nouveau';
        if ($nouveauStock <= 0 && $ancienStock > 0) return 'ancien';
        if ($ancienStock <= 0 && $nouveauStock <= 0) return 'aucun';
        
        // Si les deux stocks ont des quantités, comparer les dates
        if ($dateAncien && $dateNouveau) {
            return (strtotime($dateAncien) <= strtotime($dateNouveau)) ? 'ancien' : 'nouveau';
        }
        
        // Si une seule date est définie, utiliser ce stock
        if ($dateAncien && !$dateNouveau) return 'ancien';
        if ($dateNouveau && !$dateAncien) return 'nouveau';
        
        // Par défaut, utiliser l'ancien stock
        return 'ancien';
    }
}

/**
 * Obtient la date d'expiration active selon la logique FEFO
 * @param array $medicament Données du médicament
 * @return string|null Date d'expiration active
 */
if (!function_exists('getDateExpirationActive')) {
    function getDateExpirationActive($medicament): ?string {
        $stockPrioritaire = determinerStockPrioritaire($medicament);
        
        switch ($stockPrioritaire) {
            case 'ancien':
                return $medicament['date_expiration_ancien'] ?? null;
            case 'nouveau':
                return $medicament['date_expiration_nouveau'] ?? null;
            default:
                return null;
        }
    }
}

/**
 * Calcule le stock total disponible
 * @param array $medicament Données du médicament
 * @return int Stock total
 */
if (!function_exists('getStockTotal')) {
    function getStockTotal($medicament): int {
        return intval($medicament['ancien_stock'] ?? 0) + intval($medicament['nouveau_stock'] ?? 0);
    }
}

/**
 * Effectue une sortie de stock selon la logique FEFO
 * @param Database $db Instance de base de données
 * @param int $medicament_id ID du médicament
 * @param int $quantite_sortie Quantité à sortir
 * @param int $user_id ID de l'utilisateur
 * @param string $motif Motif de la sortie
 * @return bool Succès de l'opération
 */
if (!function_exists('effectuerSortieFEFO')) {
    function effectuerSortieFEFO($db, $medicament_id, $quantite_sortie, $user_id, $motif = 'Vente'): bool {
        // Récupérer les données actuelles du médicament
        $medicament = $db->select("SELECT * FROM medicaments WHERE id = :id", [':id' => $medicament_id])[0] ?? null;
        if (!$medicament) return false;
        
        $ancienStock = intval($medicament['ancien_stock']);
        $nouveauStock = intval($medicament['nouveau_stock']);
        $stockTotal = $ancienStock + $nouveauStock;
        
        // Vérifier si on a assez de stock
        if ($quantite_sortie > $stockTotal) return false;
        
        $quantiteRestante = $quantite_sortie;
        $nouveauAncienStock = $ancienStock;
        $nouveauNouveauStock = $nouveauStock;
        
        // Déterminer l'ordre de sortie selon FEFO
        $stockPrioritaire = determinerStockPrioritaire($medicament);
        
        if ($stockPrioritaire === 'ancien') {
            // Sortir d'abord de l'ancien stock
            $sortieAncien = min($quantiteRestante, $ancienStock);
            $nouveauAncienStock -= $sortieAncien;
            $quantiteRestante -= $sortieAncien;
            
            // Si il reste à sortir, prendre du nouveau stock
            if ($quantiteRestante > 0) {
                $nouveauNouveauStock -= $quantiteRestante;
            }
        } else {
            // Sortir d'abord du nouveau stock
            $sortieNouveau = min($quantiteRestante, $nouveauStock);
            $nouveauNouveauStock -= $sortieNouveau;
            $quantiteRestante -= $sortieNouveau;
            
            // Si il reste à sortir, prendre de l'ancien stock
            if ($quantiteRestante > 0) {
                $nouveauAncienStock -= $quantiteRestante;
            }
        }
        
        // Mettre à jour la base de données
        $query = "UPDATE medicaments SET 
                  ancien_stock = :ancien_stock, 
                  nouveau_stock = :nouveau_stock,
                  stock_actuel = :stock_total
                  WHERE id = :id";
        
        $params = [
            ':ancien_stock' => max(0, $nouveauAncienStock),
            ':nouveau_stock' => max(0, $nouveauNouveauStock),
            ':stock_total' => max(0, $nouveauAncienStock + $nouveauNouveauStock),
            ':id' => $medicament_id
        ];
        
        if ($db->execute($query, $params)) {
            // Enregistrer le mouvement de stock
            $db->execute("INSERT INTO stock_mouvements (medicament_id, type_mouvement, quantite, quantite_avant, quantite_apres, user_id, motif) 
                         VALUES (:medicament_id, 'sortie', :quantite, :quantite_avant, :quantite_apres, :user_id, :motif)", [
                ':medicament_id' => $medicament_id,
                ':quantite' => $quantite_sortie,
                ':quantite_avant' => $stockTotal,
                ':quantite_apres' => $nouveauAncienStock + $nouveauNouveauStock,
                ':user_id' => $user_id,
                ':motif' => $motif
            ]);
            return true;
        }
        
        return false;
    }
}
?>