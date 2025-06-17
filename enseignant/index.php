<?php
session_start();
require_once('../config/db.php');

// Rediriger vers le dashboard si déjà connecté
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'enseignant') {
    header('Location: dashboard.php');
    exit();
}

$error = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } else {
        // Vérifier les identifiants
        $stmt = $conn->prepare("SELECT id, password, nom, prenom, role FROM users WHERE email = ? AND role = 'enseignant'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Connexion réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nom'] = $user['nom'];
            $_SESSION['prenom'] = $user['prenom'];

            // Redirection vers le tableau de bord
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Email ou mot de passe incorrect';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Enseignant</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(120deg, #2980b9, #8e44ad);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header i {
            font-size: 3rem;
            color: #2980b9;
            margin-bottom: 1rem;
        }

        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }

        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(41, 128, 185, 0.25);
            border-color: #2980b9;
        }

        .btn-login {
            background: linear-gradient(120deg, #2980b9, #8e44ad);
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            color: white;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
            transition: transform 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .return-link {
            color: #2980b9;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-top: 1rem;
            transition: color 0.3s ease;
        }

        .return-link:hover {
            color: #8e44ad;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-card fade-in">
                    <div class="login-header">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <h2>Espace Enseignant</h2>
                        <p class="text-muted">Connectez-vous pour accéder à votre espace</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control" placeholder="Adresse email" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="Mot de passe" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-login">
                            <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="../index.php" class="return-link">
                            <i class="fas fa-arrow-left me-2"></i>Retour à l'accueil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>