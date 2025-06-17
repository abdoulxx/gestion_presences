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

// Tableau des jours de la semaine en français
$jours = [
    'lundi' => 'Lundi',
    'mardi' => 'Mardi',
    'mercredi' => 'Mercredi',
    'jeudi' => 'Jeudi',
    'vendredi' => 'Vendredi',
    'samedi' => 'Samedi'
];

// Récupérer les emplois du temps pour l'étudiant
$stmt = $conn->prepare("
    SELECT 
        e.*,
        m.nom as matiere_nom,
        CONCAT(u.prenom, ' ', u.nom) as enseignant_nom
    FROM emploi_temps e
    JOIN matieres m ON e.matiere_id = m.id
    JOIN users u ON e.enseignant_id = u.id
    WHERE e.classe_id = ?
    AND e.annee_scolaire = '2025-2026'
    ORDER BY FIELD(e.jour_semaine, 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'), e.heure_debut");
$stmt->execute([$etudiant['classe_id']]);
$emplois = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organiser les emplois du temps par jour
$emploi_temps = [];
foreach ($jours as $jour_key => $jour_nom) {
    $emploi_temps[$jour_key] = [];
}

foreach ($emplois as $e) {
    $emploi_temps[$e['jour_semaine']][] = $e;
}

// Fonction pour formater l'heure
function formatHeure($heure) {
    return date('H:i', strtotime($heure));
}

// Fonction pour obtenir la classe CSS du statut
function getStatusClass($statut) {
    switch ($statut) {
        case 'present':
            return 'success';
        case 'absent':
            return 'danger';
        case 'retard':
            return 'warning';
        default:
            return 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emploi du temps - Espace Étudiant</title>
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

        .cours-card {
            transition: transform 0.2s;
            border-left: 4px solid var(--primary-color);
        }

        .cours-card:hover {
            transform: translateY(-3px);
        }

        .status-badge {
            position: absolute;
            top: 0;
            right: 0;
            border-radius: 0 0.25rem 0 0.25rem;
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
                <a class="nav-link active" href="cours.php">
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
        <div class="container-fluid">
 
            <!-- Emploi du temps -->
            <div class="row">
                <?php foreach ($jours as $jour_key => $jour_nom): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm h-100" data-day="<?= $jour_key ?>">
                            <div class="card-header bg-primary text-white py-3">
                                <h5 class="mb-0">
                                    <i class="fas fa-calendar-day me-2"></i>
                                    <?= $jour_nom ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($emploi_temps[$jour_key])): ?>
                                    <?php foreach ($emploi_temps[$jour_key] as $cours): ?>
                                        <div class="cours-card p-3 mb-3 border-start border-4 border-primary rounded bg-light shadow-sm">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="mb-0 text-primary"><?= htmlspecialchars($cours['matiere_nom']) ?></h6>
                                                <span class="badge bg-secondary">
                                                    <?= substr($cours['heure_debut'], 0, 5) ?> - <?= substr($cours['heure_fin'], 0, 5) ?>
                                                </span>
                                            </div>
                                            <div class="text-muted small">
                                                <div class="mb-1">
                                                    <i class="fas fa-user-tie me-1"></i>
                                                    <?= htmlspecialchars($cours['enseignant_nom']) ?>
                                                </div>
                                                <?php if ($cours['salle']): ?>
                                                    <div>
                                                        <i class="fas fa-door-open me-1"></i>
                                                        <?= htmlspecialchars($cours['salle']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-calendar-xmark fs-2 mb-2 d-block"></i>
                                        <p class="mb-0">Aucun cours prévu</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activer tous les tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Highlight du jour actuel
        document.addEventListener('DOMContentLoaded', function() {
            var today = '<?= strtolower(date('l')) ?>';
            var todayCard = document.querySelector(`[data-day="${today}"]`);
            if (todayCard) {
                todayCard.classList.add('border-primary');
            }
        });
    </script>
</body>
</html>