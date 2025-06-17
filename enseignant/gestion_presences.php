<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'enseignant') {
    header('Location: ../auth/login.php');
    exit();
}

// Récupérer les cours de la semaine pour l'enseignant
$stmt = $conn->prepare("
    SELECT 
        e.*,
        m.nom as matiere_nom,
        c.nom as classe_nom,
        c.niveau as classe_niveau,
        CASE 
            WHEN EXISTS (
                SELECT 1 
                FROM presences p 
                WHERE p.cours_id = e.id 
                LIMIT 1
            ) THEN 1 
            ELSE 0 
        END as appel_fait
    FROM emploi_temps e
    JOIN matieres m ON e.matiere_id = m.id
    JOIN classes c ON e.classe_id = c.id
    WHERE e.enseignant_id = ?
    AND e.annee_scolaire = '2025-2026'
    ORDER BY FIELD(e.jour_semaine, 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'), e.heure_debut");
$stmt->execute([$_SESSION['user_id']]);
$cours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tableau des jours de la semaine en français
$jours = [
    'lundi' => 'Lundi',
    'mardi' => 'Mardi',
    'mercredi' => 'Mercredi',
    'jeudi' => 'Jeudi',
    'vendredi' => 'Vendredi',
    'samedi' => 'Samedi'
];

// Organiser les cours par jour
$cours_par_jour = [];
foreach ($jours as $jour_key => $jour_nom) {
    $cours_par_jour[$jour_key] = [];
}

foreach ($cours as $c) {
    $cours_par_jour[$c['jour_semaine']][] = $c;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Présences - Enseignant</title>
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
            transition: transform 0.2s;
        }

        .cours-card:hover {
            transform: translateY(-5px);
        }

        .btn-appel {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-appel:hover {
            background-color: var(--secondary-color);
            color: white;
        }

        .status-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
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
                <a class="nav-link active" href="gestion_presences.php">
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
                    <h4 class="mb-0">Gestion des Présences</h4>
                    <p class="text-muted mb-0">Semaine du <?= date('d/m/Y', strtotime('monday this week')) ?> au <?= date('d/m/Y', strtotime('sunday this week')) ?></p>
                </div>
            </div>

            <!-- Messages -->
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= $_SESSION['error_message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Liste des cours -->
            <?php if (empty($cours)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Aucun cours programmé cette semaine.
                </div>
            <?php else: ?>
                <?php foreach ($cours_par_jour as $jour_key => $cours_jour): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-day me-2"></i>
                                <?= $jours[$jour_key] ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($cours_jour as $cours): ?>
                                <div class="cours-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <h5 class="mb-1"><?= htmlspecialchars($cours['matiere_nom']) ?></h5>
                                            <p class="mb-0 text-muted">
                                                <i class="fas fa-graduation-cap me-1"></i>
                                                <?= htmlspecialchars($cours['classe_nom']) ?> (<?= htmlspecialchars($cours['classe_niveau']) ?>)
                                            </p>
                                        </div>
                                        <div class="col-md-3">
                                            <p class="mb-0">
                                                <i class="fas fa-clock me-1"></i>
                                                <?= substr($cours['heure_debut'], 0, 5) ?> - <?= substr($cours['heure_fin'], 0, 5) ?>
                                            </p>
                                            <?php if ($cours['salle']): ?>
                                                <p class="mb-0 text-muted">
                                                    <i class="fas fa-door-open me-1"></i>
                                                    <?= htmlspecialchars($cours['salle']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3">
                                            <?php if ($cours['appel_fait']): ?>
                                                <span class="status-badge bg-success">
                                                    <i class="fas fa-check me-1"></i>
                                                    Appel terminé
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge bg-warning">
                                                    <i class="fas fa-clock me-1"></i>
                                                    En attente
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <?php if (!$cours['appel_fait']): ?>
                                                <a href="faire_appel.php?cours_id=<?= $cours['id'] ?>" 
                                                   class="btn btn-appel">
                                                    <i class="fas fa-clipboard-check me-1"></i>
                                                    Faire l'appel
                                                </a>
                                            <?php else: ?>
                                                <a href="voir_appel.php?cours_id=<?= $cours['id'] ?>" 
                                                   class="btn btn-outline-primary">
                                                    <i class="fas fa-edit me-1"></i>
                                                    Voir/Modifier
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
