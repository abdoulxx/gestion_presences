<?php
session_start();
require_once('../config/db.php');

// Vérifier si l'étudiant est connecté
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'etudiant') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Récupérer les informations de l'étudiant
$stmt = $conn->prepare("
    SELECT u.*, i.matricule, i.classe_id, c.nom as classe_nom, c.niveau
    FROM users u
    JOIN inscriptions i ON u.id = i.etudiant_id
    JOIN classes c ON i.classe_id = c.id
    WHERE u.id = ?");
$stmt->execute([$user_id]);
$etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

// Traitement du formulaire de mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email = trim($_POST['email']);

        // Validation des champs
        if (empty($nom) || empty($prenom) || empty($email)) {
            $error_message = "Tous les champs obligatoires doivent être remplis.";
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET nom = ?, prenom = ?, email = ?
                    WHERE id = ?");
                $stmt->execute([$nom, $prenom, $email, $user_id]);
                $success_message = "Profil mis à jour avec succès.";
                
                // Mettre à jour les informations de session
                $_SESSION['nom'] = $nom;
                $_SESSION['prenom'] = $prenom;
                
                // Recharger les informations de l'étudiant
                $stmt = $conn->prepare("
                    SELECT u.*, i.matricule, i.classe_id, c.nom as classe_nom, c.niveau
                    FROM users u
                    JOIN inscriptions i ON u.id = i.etudiant_id
                    JOIN classes c ON i.classe_id = c.id
                    WHERE u.id = ?");
                $stmt->execute([$user_id]);
                $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $error_message = "Erreur lors de la mise à jour du profil.";
            }
        }
    } elseif (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Vérifier si le mot de passe actuel est correct
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!password_verify($current_password, $user['password'])) {
            $error_message = "Le mot de passe actuel est incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "Les nouveaux mots de passe ne correspondent pas.";
        } elseif (strlen($new_password) < 8) {
            $error_message = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                $success_message = "Mot de passe mis à jour avec succès.";
            } catch (PDOException $e) {
                $error_message = "Erreur lors de la mise à jour du mot de passe.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Espace Étudiant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
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

        .profile-card {
            transition: transform 0.2s;
        }

        .profile-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar position-fixed" style="width: 250px;">
        <div class="p-4">
            <div class="d-flex align-items-center mb-4">
                <i class="fas fa-user-graduate fs-2 me-2"></i>
                <div>
                    <h5 class="mb-0"><?php echo htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></h5>
                    <small class="text-light"><?php echo htmlspecialchars($etudiant['matricule']); ?></small>
                </div>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-home me-2"></i>
                    Tableau de bord
                </a>
                <a class="nav-link" href="cours.php">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Emploi du temps
                </a>
                <a class="nav-link" href="presences.php">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Mes présences
                </a>
                <a class="nav-link" href="justificatifs.php">
                    <i class="fas fa-file-alt me-2"></i>
                    Justificatifs
                </a>
                <a class="nav-link active" href="profil.php">
                    <i class="fas fa-user me-2"></i>
                    Mon profil
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
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Mon Profil</h4>
                    <p class="text-muted mb-0">
                        <?php echo htmlspecialchars($etudiant['classe_nom']); ?> - 
                        Niveau <?php echo htmlspecialchars($etudiant['niveau']); ?>
                    </p>
                </div>
            </div>

            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Informations personnelles -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm profile-card">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-user-edit me-2"></i>
                                Informations personnelles
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nom" name="nom" 
                                           value="<?php echo htmlspecialchars($etudiant['nom']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="prenom" name="prenom" 
                                           value="<?php echo htmlspecialchars($etudiant['prenom']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($etudiant['email']); ?>" required>
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
                    <div class="card shadow-sm profile-card">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-lock me-2"></i>
                                Changer le mot de passe
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Mot de passe actuel <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nouveau mot de passe <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <div class="form-text">Le mot de passe doit contenir au moins 8 caractères.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" name="update_password" class="btn btn-primary">
                                    <i class="fas fa-key me-2"></i>
                                    Changer le mot de passe
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Informations académiques -->
                <div class="col-12">
                    <div class="card shadow-sm profile-card">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-graduation-cap me-2"></i>
                                Informations académiques
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label text-muted">Matricule</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($etudiant['matricule']); ?></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label text-muted">Classe</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($etudiant['classe_nom']); ?></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label text-muted">Niveau</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($etudiant['niveau']); ?></p>
                                </div>
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
