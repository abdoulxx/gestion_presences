<?php
session_start();
require_once('../config/db.php');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $classe_id = $_POST['classe_id'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($nom) || empty($prenom) || empty($email) || empty($classe_id) || empty($password)) {
        $error = 'Tous les champs sont obligatoires';
    } elseif ($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide';
    } else {
        try {
            $conn->beginTransaction();

            // Vérifier si l'email existe déjà
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                throw new Exception('Cet email est déjà utilisé');
            }

            // Générer un matricule unique (année en cours + 4 chiffres aléatoires)
            $annee = date('Y');
            do {
                $matricule = $annee . str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $conn->prepare("SELECT matricule FROM inscriptions WHERE matricule = ?");
                $stmt->execute([$matricule]);
            } while ($stmt->rowCount() > 0);

            // Créer l'utilisateur
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, nom, prenom) VALUES (?, ?, ?, 'etudiant', ?, ?)");
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $username = strtolower($prenom[0] . $nom); // première lettre du prénom + nom
            $stmt->execute([$username, $hashed_password, $email, $nom, $prenom]);
            $user_id = $conn->lastInsertId();

            // Créer l'inscription avec le matricule
            $stmt = $conn->prepare("INSERT INTO inscriptions (etudiant_id, classe_id, matricule, date_inscription) VALUES (?, ?, ?, CURDATE())");
            $stmt->execute([$user_id, $classe_id, $matricule]);

            $conn->commit();
            $success = "Inscription réussie ! Votre matricule est : " . $matricule;

        } catch (Exception $e) {
            $conn->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Récupérer la liste des classes
$stmt = $conn->query("SELECT id, nom, niveau FROM classes ORDER BY niveau, nom");
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Nouvel Élève - Gestion des Présences</title>
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            margin: 2rem auto;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-header i {
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

        .matricule-display {
            background: var(--primary-color);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            margin: 1rem 0;
            font-size: 1.2rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="register-container">
            <div class="register-header">
                <i class="fas fa-user-plus"></i>
                <h2>Inscription Nouvel Élève</h2>
                <p class="text-muted">Remplissez le formulaire pour créer un compte</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger text-center mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success text-center mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
            <div class="matricule-display">
                Votre matricule : <?php echo htmlspecialchars($matricule); ?>
            </div>
            <div class="text-center mb-4">
                <p class="text-muted">Conservez précieusement ce matricule, il sera nécessaire pour la connexion</p>
            </div>
            <?php else: ?>
            <form method="POST" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="nom" name="nom" placeholder="Nom" required>
                            <label for="nom">Nom</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="prenom" name="prenom" placeholder="Prénom" required>
                            <label for="prenom">Prénom</label>
                        </div>
                    </div>
                </div>

                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                    <label for="email">Email</label>
                </div>

                <div class="form-floating mb-3">
                    <select class="form-select" id="classe_id" name="classe_id" required>
                        <option value="">Sélectionnez une classe</option>
                        <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo htmlspecialchars($classe['id']); ?>">
                            <?php echo htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="classe_id">Classe</label>
                </div>

                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Mot de passe" required minlength="6">
                    <label for="password">Mot de passe</label>
                </div>

                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                           placeholder="Confirmer le mot de passe" required minlength="6">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                </div>

                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-user-plus me-2"></i>S'inscrire
                    </button>
                </div>
            </form>
            <?php endif; ?>

            <div class="text-center">
                <a href="parent_login.php" class="back-link">
                    <i class="fas fa-arrow-left me-2"></i>Retour à la connexion
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
