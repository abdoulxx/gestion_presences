<?php
session_start();
require_once('../config/db.php');

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

// Récupérer les statistiques
$stats = [
    'total_etudiants' => $conn->query("SELECT COUNT(*) FROM users WHERE role = 'etudiant'")->fetchColumn(),
    'total_enseignants' => $conn->query("SELECT COUNT(*) FROM users WHERE role = 'enseignant'")->fetchColumn(),
    'total_classes' => $conn->query("SELECT COUNT(*) FROM classes")->fetchColumn(),
    'absences_aujourdhui' => $conn->query("SELECT COUNT(*) FROM presences WHERE DATE(created_at) = CURDATE() AND statut = 'absent'")->fetchColumn()
];

// Récupérer les dernières absences
$stmt = $conn->query("
    SELECT p.*, u.nom, u.prenom, c.nom as classe_nom, m.nom as matiere_nom 
    FROM presences p
    JOIN users u ON p.etudiant_id = u.id
    JOIN emploi_temps et ON p.cours_id = et.id
    JOIN classes c ON et.classe_id = c.id
    JOIN matieres m ON et.matiere_id = m.id
    WHERE p.statut = 'absent'
    ORDER BY p.created_at DESC
    LIMIT 5
");
$dernieres_absences = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les justificatifs en attente
$stmt = $conn->query("
    SELECT j.*, u.nom, u.prenom 
    FROM justificatifs j
    JOIN users u ON j.etudiant_id = u.id
    WHERE j.statut = 'en_attente'
    ORDER BY j.created_at DESC
    LIMIT 5
");
$justificatifs_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Admin - Gestion des Présences</title>
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

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-card h3 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .activity-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            margin-top: 2rem;
        }

        .activity-card .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .btn-custom {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .btn-custom:hover {
            background: var(--secondary-color);
            color: white;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .table th {
            border-top: none;
            font-weight: 600;
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
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-home me-2"></i>
                    Tableau de bord
                </a>
                <a class="nav-link" href="classes.php">
                    <i class="fas fa-chalkboard me-2"></i>
                    Classes
                </a>

                <a class="nav-link" href="etudiants.php">
                    <i class="fas fa-user-graduate me-2"></i>
                    Étudiants
                </a>
                <a class="nav-link" href="enseignants.php">
                    <i class="fas fa-chalkboard-teacher me-2"></i>
                    Enseignants
                </a>
                <a class="nav-link" href="attributions.php">
                    <i class="fas fa-tasks me-2"></i>
                    Attributions
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
                <a class="nav-link" href="parametres.php">
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
                <h4 class="mb-1">Bonjour, <?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></h4>
                <p class="text-muted mb-0">Voici un aperçu de l'activité aujourd'hui</p>
            </div>
            <div class="text-end">
                <p class="mb-0"><?php echo date('d/m/Y'); ?></p>
                <small class="text-muted"><?php echo date('H:i'); ?></small>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row g-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3><?php echo $stats['total_etudiants']; ?></h3>
                    <p class="text-muted mb-0">Étudiants inscrits</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3><?php echo $stats['total_enseignants']; ?></h3>
                    <p class="text-muted mb-0">Enseignants actifs</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <h3><?php echo $stats['total_classes']; ?></h3>
                    <p class="text-muted mb-0">Classes</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <h3><?php echo $stats['absences_aujourdhui']; ?></h3>
                    <p class="text-muted mb-0">Absences aujourd'hui</p>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="row">
            <!-- Latest Absences -->
            <div class="col-md-6">
                <div class="activity-card">
                    <div class="header">
                        <h5 class="mb-0">Dernières absences</h5>
                        <a href="presences.php" class="btn btn-custom btn-sm">
                            Voir tout
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Étudiant</th>
                                    <th>Classe</th>
                                    <th>Matière</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dernieres_absences as $absence): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($absence['prenom'] . ' ' . $absence['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($absence['classe_nom']); ?></td>
                                    <td><?php echo htmlspecialchars($absence['matiere_nom']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($absence['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pending Justifications -->
            <div class="col-md-6">
                <div class="activity-card">
                    <div class="header">
                        <h5 class="mb-0">Justificatifs en attente</h5>
                        <a href="justificatifs.php" class="btn btn-custom btn-sm">
                            Voir tout
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Étudiant</th>
                                    <th>Période</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($justificatifs_attente as $justificatif): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($justificatif['prenom'] . ' ' . $justificatif['nom']); ?></td>
                                    <td>
                                        <?php 
                                        echo date('d/m/Y', strtotime($justificatif['date_debut']));
                                        if ($justificatif['date_debut'] !== $justificatif['date_fin']) {
                                            echo ' - ' . date('d/m/Y', strtotime($justificatif['date_fin']));
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-success me-1" onclick="validerJustificatif(<?php echo $justificatif['id']; ?>)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="refuserJustificatif(<?php echo $justificatif['id']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fonctions pour gérer les justificatifs
        function validerJustificatif(id) {
            if (confirm('Voulez-vous vraiment valider ce justificatif ?')) {
                // Ajouter la logique AJAX pour valider le justificatif
                location.reload();
            }
        }

        function refuserJustificatif(id) {
            if (confirm('Voulez-vous vraiment refuser ce justificatif ?')) {
                // Ajouter la logique AJAX pour refuser le justificatif
                location.reload();
            }
        }
    </script>
</body>
</html>
