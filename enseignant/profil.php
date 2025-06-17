<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'enseignant') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = $error_message = '';

// Récupérer les informations de l'enseignant
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$enseignant = $stmt->fetch(PDO::FETCH_ASSOC);

// Traitement du formulaire de mise à jour du profil
if (isset($_POST['update_profile'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $specialite = trim($_POST['specialite']);

    // Validation des champs
    if (empty($nom) || empty($prenom) || empty($email)) {
        $error_message = "Tous les champs obligatoires doivent être remplis.";
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE users 
                SET nom = ?, prenom = ?, email = ?, specialite = ?
                WHERE id = ?");
            $stmt->execute([$nom, $prenom, $email, $specialite, $user_id]);
            $success_message = "Profil mis à jour avec succès.";
            
            // Mettre à jour les informations de session
            $_SESSION['nom'] = $nom;
            $_SESSION['prenom'] = $prenom;
            
            // Recharger les informations de l'enseignant
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $enseignant = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Code d'erreur MySQL pour duplicate entry
                $error_message = "Cette adresse email est déjà utilisée.";
            } else {
                $error_message = "Une erreur est survenue lors de la mise à jour du profil.";
            }
        }
    }
}

// Traitement du formulaire de changement de mot de passe
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Vérifier si le mot de passe actuel est correct
    if (password_verify($current_password, $enseignant['password'])) {
        if (strlen($new_password) < 8) {
            $error_message = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "Les nouveaux mots de passe ne correspondent pas.";
        } else {
            // Mettre à jour le mot de passe
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            $success_message = "Mot de passe mis à jour avec succès.";
        }
    } else {
        $error_message = "Le mot de passe actuel est incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Espace Enseignant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2C3E50;
            --secondary-color: #224abe;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 0%, #224abe 100%);
            min-height: 100vh;
            color: white;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 1rem;
            margin: 0.2rem 0;
            border-radius: 0.35rem;
            transition: all 0.3s;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.3s;
        }

        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }

        .icon-circle {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            .sidebar {
                position: fixed;
                left: -250px;
                z-index: 1000;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar position-fixed" style="width: 240px;">
        <div class="p-3">
            <div class="d-flex align-items-center mb-4 mt-2">
                <i class="fas fa-chalkboard-teacher fs-4 me-2"></i>
                <h5 class="mb-0">Espace Enseignant</h5>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link " href="dashboard.php">
                    <i class="fas fa-home me-2"></i>
                    Tableau de bord
                </a>
                <a class="nav-link" href="emploi_temps.php">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Emploi du temps
                </a>
                <a class="nav-link" href="gestion_presences.php">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Gestion présences
                </a>
                <a class="nav-link active" href="profil.php">
                    <i class="fas fa-user me-2"></i>
                    Profil
                </a>
                <a class="nav-link text-danger mt-auto" href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Déconnexion
                </a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0">Mon Profil</h4>
                    <p class="text-muted mb-0">Gérez vos informations personnelles</p>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Informations personnelles -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-white py-3">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user-edit me-2 text-primary"></i>
                                Informations personnelles
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="nom" class="form-label">Nom</label>
                                    <input type="text" class="form-control" id="nom" name="nom" 
                                           value="<?php echo htmlspecialchars($enseignant['nom']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="prenom" class="form-label">Prénom</label>
                                    <input type="text" class="form-control" id="prenom" name="prenom" 
                                           value="<?php echo htmlspecialchars($enseignant['prenom']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($enseignant['email']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="specialite" class="form-label">Spécialité</label>
                                    <input type="text" class="form-control" id="specialite" name="specialite" 
                                           value="<?php echo htmlspecialchars($enseignant['specialite']); ?>">
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    Enregistrer les modifications
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Changement de mot de passe -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-white py-3">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-lock me-2 text-primary"></i>
                                Changer le mot de passe
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Mot de passe actuel</label>
                                    <input type="password" class="form-control" id="current_password" 
                                           name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                    <input type="password" class="form-control" id="new_password" 
                                           name="new_password" required>
                                    <div class="form-text">Le mot de passe doit contenir au moins 8 caractères.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" required>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="fas fa-key me-2"></i>
                                    Changer le mot de passe
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Informations du compte -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-white py-3">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle me-2 text-primary"></i>
                                Informations du compte
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label text-muted">Matricule</label>
                                <p class="mb-0"><?php echo htmlspecialchars($enseignant['username']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Rôle</label>
                                <p class="mb-0">Enseignant</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Date d'inscription</label>
                                <p class="mb-0"><?php echo date('d/m/Y', strtotime($enseignant['created_at'])); ?></p>
                            </div>
                            <div class="mb-0">
                                <label class="form-label text-muted">Dernière mise à jour</label>
                                <p class="mb-0"><?php echo date('d/m/Y', strtotime($enseignant['updated_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
