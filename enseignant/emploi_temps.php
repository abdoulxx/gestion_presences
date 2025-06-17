<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'enseignant') {
    header('Location: ../auth/login.php');
    exit();
}

// Tableau des jours de la semaine en français
$jours = [
    'lundi' => 'Lundi',
    'mardi' => 'Mardi',
    'mercredi' => 'Mercredi',
    'jeudi' => 'Jeudi',
    'vendredi' => 'Vendredi',
    'samedi' => 'Samedi'
];

// Récupérer les emplois du temps pour l'enseignant
$stmt = $conn->prepare("
    SELECT 
        e.*,
        m.nom as matiere_nom,
        c.nom as classe_nom,
        c.niveau as classe_niveau
    FROM emploi_temps e
    JOIN matieres m ON e.matiere_id = m.id
    JOIN classes c ON e.classe_id = c.id
    WHERE e.enseignant_id = ?
    AND e.annee_scolaire = '2025-2026'
    ORDER BY FIELD(e.jour_semaine, 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'), e.heure_debut");
$stmt->execute([$_SESSION['user_id']]);
$emplois = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organiser les emplois du temps par jour
$emploi_temps = [];
foreach ($jours as $jour_key => $jour_nom) {
    $emploi_temps[$jour_key] = [];
}

foreach ($emplois as $e) {
    $emploi_temps[$e['jour_semaine']][] = $e;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emploi du temps - Enseignant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
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

        .main-content {
            margin-left: 240px;
            padding: 2rem;
        }

        .cours-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s;
        }

        .cours-card:hover {
            transform: translateY(-5px);
        }

        .btn-presence {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-presence:hover {
            background-color: var(--secondary-color);
            color: white;
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
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-home me-2"></i>
                    Tableau de bord
                </a>
                <a class="nav-link active" href="emploi_temps.php">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Emploi du temps
                </a>
                <a class="nav-link" href="gestion_presences.php">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Gestion présences
                </a>
                <a class="nav-link" href="profil.php">
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
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0">Mon Emploi du Temps</h4>
                    <p class="text-muted mb-0">Année scolaire 2025-2026</p>
                </div>
            </div>

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
                                                    <i class="fas fa-graduation-cap me-1"></i>
                                                    <?= htmlspecialchars($cours['classe_nom']) ?> (<?= htmlspecialchars($cours['classe_niveau']) ?>)
                                                </div>
                                                <?php if ($cours['salle']): ?>
                                                    <div>
                                                        <i class="fas fa-door-open me-1"></i>
                                                        <?= htmlspecialchars($cours['salle']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (strtolower(date('l')) === $jour_key): ?>
                                                <div class="mt-2">
                                                    <a href="faire_appel.php?cours_id=<?= $cours['id'] ?>" 
                                                       class="btn btn-sm btn-primary w-100">
                                                        <i class="fas fa-clipboard-check me-1"></i>
                                                        Faire l'appel
                                                    </a>
                                                </div>
                                            <?php endif; ?>
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
