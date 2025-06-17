<?php
session_start();
require_once('../config/db.php');

// Vérifier si l'étudiant est connecté
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'etudiant') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'étudiant
$stmt = $conn->prepare("
    SELECT u.*, i.matricule, i.classe_id, c.nom as classe_nom, c.niveau
    FROM users u
    JOIN inscriptions i ON u.id = i.etudiant_id
    JOIN classes c ON i.classe_id = c.id
    WHERE u.id = ?");
$stmt->execute([$user_id]);
$etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les cours d'aujourd'hui
$jour_semaine = strtolower(date('l')); // Jour actuel en anglais
$stmt = $conn->prepare("
    SELECT 
        e.*, 
        m.nom as matiere_nom, 
        u.nom as enseignant_nom, 
        u.prenom as enseignant_prenom,
        COALESCE(p.statut, 'non_commence') as statut_presence
    FROM emploi_temps e
    JOIN matieres m ON e.matiere_id = m.id
    JOIN users u ON e.enseignant_id = u.id
    LEFT JOIN presences p ON e.id = p.cours_id 
        AND p.etudiant_id = ? 
        AND p.date = CURDATE()
    WHERE e.classe_id = ? 
    AND e.jour_semaine = ?
    ORDER BY e.heure_debut");
$stmt->execute([$user_id, $etudiant['classe_id'], $jour_semaine]);
$cours_aujourdhui = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les statistiques de présence
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT p.cours_id, p.date) as total_cours,
        SUM(CASE WHEN p.statut = 'present' THEN 1 ELSE 0 END) as presents,
        SUM(CASE WHEN p.statut = 'absent' THEN 1 ELSE 0 END) as absents,
        SUM(CASE WHEN p.statut = 'retard' THEN 1 ELSE 0 END) as retards
    FROM presences p
    JOIN emploi_temps e ON p.cours_id = e.id
    WHERE e.classe_id = ? 
    AND p.etudiant_id = ? 
    AND p.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute([$etudiant['classe_id'], $user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculer les pourcentages
$total_cours = $stats['total_cours'];
$taux_presence = $total_cours > 0 ? round(($stats['presents'] / $total_cours) * 100) : 0;
$taux_absence = $total_cours > 0 ? round(($stats['absents'] / $total_cours) * 100) : 0;
$taux_retard = $total_cours > 0 ? round(($stats['retards'] / $total_cours) * 100) : 0;

// Récupérer les dernières notifications
$stmt = $conn->prepare("
    SELECT *
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les justificatifs en cours
$stmt = $conn->prepare("
    SELECT *
    FROM justificatifs
    WHERE etudiant_id = ?
    ORDER BY created_at DESC
    LIMIT 5");
$stmt->execute([$user_id]);
$justificatifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Espace Étudiant</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
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
            padding: 1.5rem;
        }

        .stat-card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem rgba(58, 59, 69, 0.15);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .cours-card {
            border-left: 0.25rem solid var(--primary-color);
        }

        .notification-card {
            border-left: 0.25rem solid var(--info-color);
        }

        .justificatif-card {
            border-left: 0.25rem solid var(--warning-color);
        }

        .progress {
            height: 0.5rem;
            margin-top: 0.5rem;
        }

        .status-badge {
            padding: 0.35rem 0.5rem;
            border-radius: 0.35rem;
            font-size: 0.8rem;
        }

        .status-present {
            background-color: var(--success-color);
            color: white;
        }

        .status-absent {
            background-color: var(--danger-color);
            color: white;
        }

        .status-retard {
            background-color: var(--warning-color);
            color: white;
        }

        .status-non-commence {
            background-color: var(--secondary-color);
            color: white;
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
                <a class="nav-link active" href="dashboard.php">
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
                <a class="nav-link" href="profil.php">
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
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Tableau de Bord</h4>
                <p class="text-muted mb-0">
                    <?php echo htmlspecialchars($etudiant['niveau'] . ' - ' . $etudiant['classe_nom']); ?>
                </p>
            </div>
            <div class="text-end">
                <p class="mb-0"><strong><?php echo date('d/m/Y'); ?></strong></p>
                <p class="text-muted mb-0"><?php echo date('H:i'); ?></p>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-primary mb-0">Taux de Présence</h6>
                                <h2 class="mb-0"><?php echo $taux_presence; ?>%</h2>
                            </div>
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                                <i class="fas fa-check text-primary"></i>
                            </div>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-primary" style="width: <?php echo $taux_presence; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-danger mb-0">Absences</h6>
                                <h2 class="mb-0"><?php echo $stats['absents']; ?></h2>
                            </div>
                            <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                                <i class="fas fa-times text-danger"></i>
                            </div>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-danger" style="width: <?php echo $taux_absence; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-warning mb-0">Retards</h6>
                                <h2 class="mb-0"><?php echo $stats['retards']; ?></h2>
                            </div>
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                                <i class="fas fa-clock text-warning"></i>
                            </div>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-warning" style="width: <?php echo $taux_retard; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-info mb-0">Total Cours</h6>
                                <h2 class="mb-0"><?php echo $total_cours; ?></h2>
                            </div>
                            <div class="rounded-circle bg-info bg-opacity-10 p-3">
                                <i class="fas fa-book text-info"></i>
                            </div>
                        </div>
                        <small class="text-muted">Depuis le début de l'année</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cours d'aujourd'hui et Notifications -->
        <div class="row g-4">
            <!-- Cours d'aujourd'hui -->
            <div class="col-md-8">
                <div class="card cours-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Cours d'aujourd'hui</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cours_aujourdhui)): ?>
                        <p class="text-muted mb-0">Aucun cours prévu aujourd'hui</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Horaire</th>
                                        <th>Matière</th>
                                        <th>Enseignant</th>
                                        <th>Salle</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cours_aujourdhui as $cours): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            echo substr($cours['heure_debut'], 0, 5) . ' - ' . 
                                                 substr($cours['heure_fin'], 0, 5); 
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($cours['matiere_nom']); ?></td>
                                        <td>
                                            <?php 
                                            echo htmlspecialchars($cours['enseignant_prenom'] . ' ' . 
                                                                 $cours['enseignant_nom']); 
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($cours['salle']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $cours['statut_presence']; ?>">
                                                <?php 
                                                $statuts = [
                                                    'present' => 'Présent',
                                                    'absent' => 'Absent',
                                                    'retard' => 'En retard',
                                                    'non_commence' => 'Non commencé'
                                                ];
                                                echo $statuts[$cours['statut_presence']];
                                                ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Notifications et Justificatifs -->

                <!-- Justificatifs -->
                <div class="card justificatif-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Justificatifs récents</h5>
                        <a href="justificatifs.php" class="btn btn-sm btn-outline-warning">
                            <i class="fas fa-plus"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($justificatifs)): ?>
                        <p class="text-muted mb-0">Aucun justificatif</p>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($justificatifs as $justif): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <?php 
                                        echo date('d/m/Y', strtotime($justif['date_debut'])) . ' - ' . 
                                             date('d/m/Y', strtotime($justif['date_fin'])); 
                                        ?>
                                    </h6>
                                    <span class="badge bg-<?php 
                                        echo $justif['statut'] === 'accepte' ? 'success' : 
                                            ($justif['statut'] === 'refuse' ? 'danger' : 'warning'); 
                                        ?>">
                                        <?php echo ucfirst($justif['statut']); ?>
                                    </span>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($justif['motif']); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Mettre à jour l'heure en temps réel
        function updateTime() {
            const now = new Date();
            const time = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
            document.querySelector('.text-muted.mb-0').textContent = time;
        }

        setInterval(updateTime, 1000);

        // Activer les tooltips Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>