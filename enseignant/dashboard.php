<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'enseignant') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'enseignant
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$enseignant = $stmt->fetch(PDO::FETCH_ASSOC);

// Statistiques des cours d'aujourd'hui
$date_aujourdhui = date('Y-m-d');
$jour_semaine = strtolower(date('l'));

$stmt = $conn->prepare("
    SELECT COUNT(*) as total_cours_jour
    FROM emploi_temps
    WHERE enseignant_id = ? AND jour_semaine = ?
    AND annee_scolaire = '2025-2026'");
$stmt->execute([$user_id, $jour_semaine]);
$cours_jour = $stmt->fetch(PDO::FETCH_ASSOC)['total_cours_jour'];

// Nombre total de cours pour cet enseignant
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_cours
    FROM emploi_temps
    WHERE enseignant_id = ?
    AND annee_scolaire = '2025-2026'");
$stmt->execute([$user_id]);
$total_cours = $stmt->fetch(PDO::FETCH_ASSOC)['total_cours'];

// Nombre de classes différentes
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT classe_id) as total_classes
    FROM emploi_temps
    WHERE enseignant_id = ?
    AND annee_scolaire = '2025-2026'");
$stmt->execute([$user_id]);
$total_classes = $stmt->fetch(PDO::FETCH_ASSOC)['total_classes'];

// Nombre de matières différentes enseignées
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT matiere_id) as total_matieres
    FROM emploi_temps
    WHERE enseignant_id = ?
    AND annee_scolaire = '2025-2026'");
$stmt->execute([$user_id]);
$total_matieres = $stmt->fetch(PDO::FETCH_ASSOC)['total_matieres'];

// Statistiques de présence pour aujourd'hui
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_presences,
        SUM(CASE WHEN statut = 'present' THEN 1 ELSE 0 END) as presents,
        SUM(CASE WHEN statut = 'absent' THEN 1 ELSE 0 END) as absents,
        SUM(CASE WHEN statut = 'retard' THEN 1 ELSE 0 END) as retards
    FROM presences p
    JOIN emploi_temps e ON p.cours_id = e.id
    WHERE e.enseignant_id = ? AND p.date = ?");
$stmt->execute([$user_id, $date_aujourdhui]);
$stats_jour = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les prochains cours d'aujourd'hui
$heure_actuelle = date('H:i:s');
$stmt = $conn->prepare("
    SELECT 
        e.id, e.heure_debut, e.heure_fin, e.salle,
        m.nom as matiere_nom,
        c.nom as classe_nom,
        c.niveau as classe_niveau
    FROM emploi_temps e
    JOIN matieres m ON e.matiere_id = m.id
    JOIN classes c ON e.classe_id = c.id
    WHERE e.enseignant_id = ? 
    AND e.jour_semaine = ?
    AND e.heure_debut > ?
    AND e.annee_scolaire = '2025-2026'
    ORDER BY e.heure_debut
    LIMIT 5");
$stmt->execute([$user_id, $jour_semaine, $heure_actuelle]);
$prochains_cours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les dernières présences enregistrées
$stmt = $conn->prepare("
    SELECT 
        p.date, p.statut,
        m.nom as matiere_nom,
        c.nom as classe_nom,
        CONCAT(u.prenom, ' ', u.nom) as etudiant_nom
    FROM presences p
    JOIN emploi_temps e ON p.cours_id = e.id
    JOIN matieres m ON e.matiere_id = m.id
    JOIN classes c ON e.classe_id = c.id
    JOIN users u ON p.etudiant_id = u.id
    WHERE e.enseignant_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5");
$stmt->execute([$user_id]);
$dernieres_presences = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les cours du jour
$jour_semaine = strtolower(date('l')); // Jour actuel en anglais
$stmt = $conn->prepare("
    SELECT 
        e.*, 
        m.nom as matiere_nom, 
        c.nom as classe_nom, 
        c.niveau 
    FROM emploi_temps e
    JOIN matieres m ON e.matiere_id = m.id
    JOIN classes c ON e.classe_id = c.id
    WHERE e.enseignant_id = ? 
    AND e.jour_semaine = ?
    ORDER BY e.heure_debut
");
$stmt->execute([$user_id, $jour_semaine]);
$cours_jour = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Enseignant - Gestion des Présences</title>
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
                <a class="nav-link active" href="dashboard.php">
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
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Bienvenue, <?php echo htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']); ?></h4>
                <p class="text-muted mb-0">Voici le résumé de votre journée</p>
            </div>
            <div class="text-end">
                <p class="mb-0"><?php echo date('d/m/Y'); ?></p>
                <small class="text-muted"><?php echo date('H:i'); ?></small>
            </div>
        </div>

        <!-- Cartes de statistiques -->
        <div class="row g-4 mb-4">

            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-circle bg-success text-white me-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <h6 class="card-title mb-0">Classes</h6>
                        </div>
                        <h2 class="mb-0"><?php echo $total_classes; ?></h2>
                        <small class="text-muted">classes assignées</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-circle bg-info text-white me-3">
                                <i class="fas fa-book"></i>
                            </div>
                            <h6 class="card-title mb-0">Matières</h6>
                        </div>
                        <h2 class="mb-0"><?php echo $total_matieres; ?></h2>
                        <small class="text-muted">matières enseignées</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-circle bg-warning text-white me-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h6 class="card-title mb-0">Total Cours</h6>
                        </div>
                        <h2 class="mb-0"><?php echo $total_cours; ?></h2>
                        <small class="text-muted">cours par semaine</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Prochains cours -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar-alt me-2 text-primary"></i>
                            Prochains cours aujourd'hui
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($prochains_cours)): ?>
                            <p class="text-muted text-center py-3">Aucun cours à venir aujourd'hui</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($prochains_cours as $cours): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($cours['matiere_nom']); ?></h6>
                                            <small class="text-primary">
                                                <?php echo substr($cours['heure_debut'], 0, 5) . ' - ' . substr($cours['heure_fin'], 0, 5); ?>
                                            </small>
                                        </div>
                                        <p class="mb-1">
                                            <?php echo htmlspecialchars($cours['classe_nom']); ?> - 
                                            Niveau <?php echo htmlspecialchars($cours['classe_niveau']); ?>
                                        </p>
                                        <?php if ($cours['salle']): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                Salle <?php echo htmlspecialchars($cours['salle']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Dernières présences -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-clipboard-check me-2 text-primary"></i>
                            Dernières présences enregistrées
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($dernieres_presences)): ?>
                            <p class="text-muted text-center py-3">Aucune présence enregistrée</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($dernieres_presences as $presence): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($presence['etudiant_nom']); ?></h6>
                                            <span class="badge bg-<?php 
                                                echo $presence['statut'] === 'present' ? 'success' : 
                                                    ($presence['statut'] === 'absent' ? 'danger' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst($presence['statut']); ?>
                                            </span>
                                        </div>
                                        <p class="mb-1">
                                            <?php echo htmlspecialchars($presence['matiere_nom']); ?> - 
                                            <?php echo htmlspecialchars($presence['classe_nom']); ?>
                                        </p>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y', strtotime($presence['date'])); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
