<?php
require_once __DIR__ . '/functions.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? escape($pageTitle) : 'Pharmacie Management' ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/pharma-app/assets/css/style.css" rel="stylesheet">
    <link href="/pharma-app/assets/css/responsive.css" rel="stylesheet">
    
    <!-- Theme: Light theme only, dark theme functionality removed -->
    
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" style="padding-right: 50px;" href="<?= $_SESSION['user_role'] === 'directeur' ? '/pharma-app/admin/dashboard.php' : ($_SESSION['user_role'] === 'magasinier' ? '/pharma-app/magasinier/dashboard.php' : '/pharma-app/caissier/dashboard.php') ?>">
                <i class="bi bi-capsule-pill me-2"></i>
                FIANGEP Pharma
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if ($_SESSION['user_role'] === 'directeur'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/admin/dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/admin/produits.php">
                                <i class="bi bi-capsule"></i> Médicaments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/admin/fournisseurs.php">
                                <i class="bi bi-truck"></i> Fournisseurs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/admin/personnel.php">
                                <i class="bi bi-people"></i> Personnel
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/admin/ventes.php">
                                <i class="bi bi-cart"></i> Ventes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/admin/commandes_fournisseurs.php">
                                <i class="bi bi-file-earmark-text"></i> Commandes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/admin/stock_alert.php">
                                <i class="bi bi-exclamation-triangle"></i> Alertes Stock
                            </a>
                        </li>
                    <?php elseif ($_SESSION['user_role'] === 'magasinier'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/magasinier/dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/magasinier/produits.php">
                                <i class="bi bi-capsule"></i> Médicaments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/magasinier/stock_mouvement.php">
                                <i class="bi bi-arrow-up-down"></i> Stock
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/magasinier/fournisseurs.php">
                                <i class="bi bi-truck"></i> Fournisseurs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/magasinier/commandes_fournisseurs.php">
                                <i class="bi bi-file-earmark-text"></i> Commandes
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/caissier/dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/caissier/produits.php">
                                <i class="bi bi-capsule"></i> Médicaments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/caissier/clients.php">
                                <i class="bi bi-person"></i> Clients
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/caissier/facture.php">
                                <i class="bi bi-receipt"></i> Facturation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="/pharma-app/caissier/stock_mouvement.php">
                                <i class="bi bi-arrow-up-down"></i> Stock
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center text-light" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            <span class="d-none d-md-inline ms-2 text-light"><?= escape($_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom']) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text">
                                <strong><?= escape($_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom']) ?></strong><br>
                                <small class="text-muted">Rôle: <?= ucfirst($_SESSION['user_role']) ?></small>
                            </span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/pharma-app/auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Déconnexion
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main content wrapper -->
    <div class="main-content" style="padding-top:0px;">
        <div class="container-fluid">