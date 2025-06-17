<?php
session_start();
require_once('../config/db.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } else {
        // Recherche par matricule
        $stmt = $conn->prepare("SELECT u.* FROM users u 
                              JOIN inscriptions i ON u.id = i.etudiant_id 
                              WHERE i.matricule = ? AND u.role = 'etudiant'");

        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nom'] = $user['nom'];
            $_SESSION['prenom'] = $user['prenom'];

            // Redirection selon le rôle
            switch($user['role']) {
                case 'admin':
                    header('Location: ../admin/dashboard.php');
                    break;
                case 'enseignant':
                    header('Location: ../enseignant/dashboard.php');
                    break;
                case 'etudiant':
                    header('Location: ../etudiant/dashboard.php');
                    break;
            }
            exit();
        } else {
            $error = 'Identifiants incorrects';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Parents/Étudiants - Gestion des Présences</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #764ba2;
            --secondary-color: #667eea;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .form-floating {
            margin-bottom: 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(118, 75, 162, 0.25);
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
        }

        .back-link {
            color: var(--primary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-top: 1rem;
            font-weight: 500;
        }

        .back-link:hover {
            color: var(--secondary-color);
        }

        .alert {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body>
    <div class="container min-vh-100 d-flex align-items-center justify-content-center">
        <div class="login-container">
            <div class="login-header">
                <i class="fas fa-user-graduate"></i>
                <h2>Espace Parents/Étudiants</h2>
                <p class="text-muted">Accédez au suivi de scolarité</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger text-center mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Matricule de l'élève" required>
                    <label for="username">Matricule de l'élève</label>
                </div>

                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Mot de passe" required>
                    <label for="password">Mot de passe</label>
                </div>

                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                    </button>
                </div>
            </form>

            <div class="text-center mt-3">
                <div class="mb-3">
                    <a href="register.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-user-plus me-2"></i>Inscription nouvel élève
                    </a>
                </div>
                <a href="../index.php" class="back-link">
                    <i class="fas fa-arrow-left me-2"></i>Retour à l'accueil
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation des formulaires Bootstrap
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html>