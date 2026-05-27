<?php
/**
 * Page d'accueil - redirection automatique
 */
session_start();

// Si l'utilisateur est déjà connecté, rediriger vers le dashboard approprié
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'directeur') {
        header('Location: /pharma-app/admin/dashboard.php');
    } elseif ($_SESSION['user_role'] === 'magasinier') {
        header('Location: /pharma-app/magasinier/dashboard.php');
    } else {
        header('Location: /pharma-app/caissier/dashboard.php');
    }
    exit();
}

// Sinon, rediriger vers la page de connexion
header('Location: /pharma-app/auth/login.php');
exit();
?>