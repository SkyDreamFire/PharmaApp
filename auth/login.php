<?php
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    redirectByRole();
    exit;
}

// Génération du token CSRF si nécessaire
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Vérification du token CSRF
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = "Requête invalide. Veuillez actualiser la page.";
    } elseif (empty($usernameOrEmail) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $db = Database::getInstance();

        // Requête avec deux paramètres distincts
        $query = "
            SELECT id, username, email, password, role, nom, prenom, actif
            FROM users
            WHERE (username = :username OR email = :email) AND actif = 1
            LIMIT 1
        ";

        $params = [
            ':username' => $usernameOrEmail,
            ':email' => $usernameOrEmail
        ];

        $user = $db->selectOne($query, $params);

        if ($user && password_verify($password, $user['password'])) {
            // Rehash du mot de passe si nécessaire
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $db->update(
                    "UPDATE users SET password = :newPassword WHERE id = :id",
                    [':newPassword' => $newHash, ':id' => $user['id']]
                );
            }

            // Initialisation de la session utilisateur
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_email']    = $user['email'];
            $_SESSION['user_role']     = $user['role'];
            $_SESSION['user_nom']      = $user['nom'];
            $_SESSION['user_prenom']   = $user['prenom'];

            redirectByRole();
            exit;
        } else {
            $error = "Nom d'utilisateur, email ou mot de passe incorrect.";
        }
    }
}
?>



<!DOCTYPE html>
<html lang="fr" data-bs-theme="auto">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Pharmacie Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/pharma-app/assets/css/style.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 1rem;
            box-shadow: 0 2rem 4rem rgba(0, 0, 0, 0.15);
            max-width: 420px;
            width: 100%;
        }
        
        [data-bs-theme="dark"] .login-card {
            background: rgba(33, 37, 41, 0.95);
        }
        
        .brand-logo {
            font-size: 3rem;
            color: var(--bs-primary);
            margin-bottom: 1rem;
        }
        
        .login-form .form-control {
            border: 2px solid transparent;
            background-color: rgba(var(--bs-body-color-rgb), 0.05);
            padding: 0.75rem 1rem;
        }
        
        .login-form .form-control:focus {
            background-color: rgba(var(--bs-body-color-rgb), 0.08);
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .demo-credentials {
            font-size: 0.875rem;
            line-height: 1.4;
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            body {
                padding: 0.5rem;
            }
            
            .login-card {
                border-radius: 0.5rem;
            }
            
            .card-body {
                padding: 2rem 1.5rem !important;
            }
            
            .brand-logo {
                font-size: 2.5rem;
            }
            
            .h3 {
                font-size: 1.5rem;
            }
            
            .demo-credentials {
                font-size: 0.8rem;
            }
            
            .form-control, .btn {
                font-size: 16px; /* Évite le zoom sur iOS */
                min-height: 44px;
            }
        }
    </style>
</head>
<body>
    <div class="login-card card">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <i class="bi bi-capsule-pill brand-logo"></i>
                <h2 class="h3 mb-3 font-weight-normal">Pharmacie Management</h2>
                <p class="text-muted">Connectez-vous à votre compte</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <?= escape($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
    <!-- Champ caché CSRF ajouté ici -->
    <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">

    <div class="mb-3">
        <label for="username" class="form-label">Nom d'utilisateur ou Email</label>
        <div class="input-group">
            <span class="input-group-text bg-transparent border-end-0">
                <i class="bi bi-person"></i>
            </span>
            <input type="text" 
                   class="form-control border-start-0" 
                   id="username" 
                   name="username" 
                   required 
                   autocomplete="username"
                   value="<?= escape($_POST['username'] ?? '') ?>">
        </div>
    </div>

    <div class="mb-4">
        <label for="password" class="form-label">Mot de passe</label>
        <div class="input-group">
            <span class="input-group-text bg-transparent border-end-0">
                <i class="bi bi-lock"></i>
            </span>
            <input type="password" 
                   class="form-control border-start-0" 
                   id="password" 
                   name="password" 
                   required 
                   autocomplete="current-password">
            <button class="btn btn-outline-secondary border-start-0" 
                    type="button" 
                    onclick="togglePassword()">
                <i class="bi bi-eye" id="toggleIcon"></i>
            </button>
        </div>
    </div>

    <button type="submit" class="btn btn-login btn-primary w-100 mb-3">
        <i class="bi bi-box-arrow-in-right me-2"></i>
        Se connecter
    </button>
</form>

            <div class="demo-credentials bg-light p-3 rounded">
                <h6 class="text-muted mb-2">
                    <i class="bi bi-info-circle me-1"></i>
                    Comptes de démonstration
                </h6>
                <div class="row">
                    <div class="col-6">
                        <strong>Directeur:</strong><br>
                        <small>admin / password</small>
                    </div>
                    <div class="col-6">
                        <strong>Caissier:</strong><br>
                        <small>caissier1 / password</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        }
        
        // Theme detection
        const getStoredTheme = () => localStorage.getItem('theme')
        const setStoredTheme = theme => localStorage.setItem('theme', theme)
        
        const getPreferredTheme = () => {
            const storedTheme = getStoredTheme()
            if (storedTheme) {
                return storedTheme
            }
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
        }
        
        const setTheme = theme => {
            document.documentElement.setAttribute('data-bs-theme', theme)
        }
        
        setTheme(getPreferredTheme())
    </script>
</body>
</html>