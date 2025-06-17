<?php
session_start();
require_once('../config/db.php');

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}
$success_message = '';
$error_message = '';

// Traitement du formulaire d'ajout d'admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_admin') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $email = trim($_POST['email']);
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);

        // Vérification si l'username ou l'email existe déjà
        $stmt = $conn->prepare("SELECT COUNT(*) FROM admins WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $error_message = "Cet username ou email existe déjà.";
        } else {
            // Hash du mot de passe
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insertion du nouvel admin
            $stmt = $conn->prepare("INSERT INTO admins (username, password, email, nom, prenom) VALUES (?, ?, ?, ?, ?)");
            
            try {
                $stmt->execute([$username, $hashed_password, $email, $nom, $prenom]);
                $success_message = "L'administrateur a été ajouté avec succès.";
            } catch(PDOException $e) {
                $error_message = "Erreur lors de l'ajout de l'administrateur.";
            }
        }
    }
}

// Récupération de la liste des admins
$stmt = $conn->query("SELECT id, username, email, nom, prenom, created_at FROM admins ORDER BY created_at DESC");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Gestion des Présences</title>
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            color: white;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 0.8rem 1rem;
            margin: 0.2rem 0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar .nav-link i {
            width: 24px;
        }

        .main-content {
            margin-left: 240px;
            padding: 2rem;
        }

        .activity-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            margin-bottom: 2rem;
        }

        .btn-custom {
            background: var(--primary-color);
            color: white;
        }

        .btn-custom:hover {
            background: var(--secondary-color);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar position-fixed" style="width: 240px;">
        <div class="p-3">
            <div class="d-flex align-items-center mb-4 mt-2">
                <i class="fas fa-user-shield fs-4 me-2"></i>
                <h5 class="mb-0">Administration</h5>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-home me-2"></i>
                    Tableau de bord
                </a>
                <a class="nav-link" href="etudiants.php">
                    <i class="fas fa-user-graduate me-2"></i>
                    Étudiants
                </a>
                <a class="nav-link" href="enseignants.php">
                    <i class="fas fa-chalkboard-teacher me-2"></i>
                    Enseignants
                </a>
                <a class="nav-link" href="classes.php">
                    <i class="fas fa-school me-2"></i>
                    Classes
                </a>
                <a class="nav-link" href="matieres.php">
                    <i class="fas fa-book me-2"></i>
                    Matières
                </a>
                <a class="nav-link" href="emploi_temps.php">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Emploi du temps
                </a>
                <a class="nav-link" href="presences.php">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Présences
                </a>
                <a class="nav-link" href="justificatifs.php">
                    <i class="fas fa-file-alt me-2"></i>
                    Justificatifs
                </a>
                <a class="nav-link active" href="parametres.php">
                    <i class="fas fa-cog me-2"></i>
                    Paramètres
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
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0">Paramètres</h3>
                <p class="text-muted">Gérer les paramètres de l'application</p>
            </div>
            <div class="text-end">
                <p class="mb-0"><?php echo date('d/m/Y'); ?></p>
                <small class="text-muted"><?php echo date('H:i'); ?></small>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Add Admin Form -->
        <div class="activity-card">
            <h5 class="card-title mb-4">Ajouter un nouvel administrateur</h5>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_admin">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">Nom d'utilisateur</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Mot de passe</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="nom" class="form-label">Nom</label>
                        <input type="text" class="form-control" id="nom" name="nom" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="prenom" class="form-label">Prénom</label>
                        <input type="text" class="form-control" id="prenom" name="prenom" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-custom">Ajouter l'administrateur</button>
            </form>
        </div>

        <!-- Admin List -->
        <div class="activity-card">
            <h5 class="card-title mb-4">Liste des administrateurs</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nom d'utilisateur</th>
                            <th>Email</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Date de création</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td><?php echo htmlspecialchars($admin['nom']); ?></td>
                                <td><?php echo htmlspecialchars($admin['prenom']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($admin['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
