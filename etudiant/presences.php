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

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtres
$matiere_id = isset($_GET['matiere']) ? (int)$_GET['matiere'] : null;
$statut = isset($_GET['statut']) ? $_GET['statut'] : null;
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : null;
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : null;

// Construction de la requête
$params = [$user_id];
$where_clauses = ["p.etudiant_id = ?"];

if ($matiere_id) {
    $where_clauses[] = "c.matiere_id = ?";
    $params[] = $matiere_id;
}

if ($statut) {
    $where_clauses[] = "p.statut = ?";
    $params[] = $statut;
}

if ($date_debut) {
    $where_clauses[] = "p.date >= ?";
    $params[] = $date_debut;
}

if ($date_fin) {
    $where_clauses[] = "p.date <= ?";
    $params[] = $date_fin;
}

$where_clause = implode(" AND ", $where_clauses);

// Récupérer le total des enregistrements
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM presences p
    JOIN emploi_temps e ON p.cours_id = e.id
    WHERE " . $where_clause);
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $limit);

// Récupérer les présences
$sql = "SELECT 
        p.id, p.statut, p.created_at, p.date, p.commentaire,
        e.heure_debut, e.heure_fin,
        m.nom as matiere_nom,
        CONCAT(u.prenom, ' ', u.nom) as enseignant_nom
    FROM presences p
    JOIN emploi_temps e ON p.cours_id = e.id
    JOIN matieres m ON e.matiere_id = m.id
    JOIN users u ON e.enseignant_id = u.id
    WHERE " . $where_clause . "
    ORDER BY p.date DESC, e.heure_debut DESC
    LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$presences = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des matières pour le filtre
$stmt = $conn->prepare("
    SELECT DISTINCT m.id, m.nom
    FROM matieres m
    JOIN emploi_temps e ON m.id = e.matiere_id
    JOIN presences p ON e.id = p.cours_id
    WHERE p.etudiant_id = ?
    ORDER BY m.nom");
$stmt->execute([$user_id]);
$matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Historique des Présences - Espace Étudiant</title>
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

        .presence-row {
            transition: background-color 0.2s;
        }

        .presence-row:hover {
            background-color: rgba(0,0,0,0.02);
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
                <a class="nav-link " href="dashboard.php">
                    <i class="fas fa-home me-2"></i>
                    Tableau de bord
                </a>
                <a class="nav-link" href="cours.php">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Emploi du temps
                </a>
                <a class="nav-link active" href="presences.php">
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
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Historique des Présences</h4>
                    <p class="text-muted mb-0">
                        <?php echo htmlspecialchars($etudiant['classe_nom']); ?> - 
                        Niveau <?php echo htmlspecialchars($etudiant['niveau']); ?>
                    </p>
                </div>
            </div>

            <!-- Filtres -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Matière</label>
                            <select name="matiere" class="form-select">
                                <option value="">Toutes les matières</option>
                                <?php foreach ($matieres as $m): ?>
                                <option value="<?php echo $m['id']; ?>" 
                                        <?php echo $matiere_id == $m['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m['nom']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Statut</label>
                            <select name="statut" class="form-select">
                                <option value="">Tous les statuts</option>
                                <option value="present" <?php echo $statut === 'present' ? 'selected' : ''; ?>>Présent</option>
                                <option value="absent" <?php echo $statut === 'absent' ? 'selected' : ''; ?>>Absent</option>
                                <option value="retard" <?php echo $statut === 'retard' ? 'selected' : ''; ?>>Retard</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date début</label>
                            <input type="date" name="date_debut" class="form-control" 
                                   value="<?php echo $date_debut; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date fin</label>
                            <input type="date" name="date_fin" class="form-control" 
                                   value="<?php echo $date_fin; ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-2"></i>Filtrer
                            </button>
                            <a href="presences.php" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-2"></i>Réinitialiser
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Liste des présences -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (empty($presences)): ?>
                        <p class="text-center text-muted py-4">Aucun enregistrement trouvé</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Horaire</th>
                                        <th>Matière</th>
                                        <th>Enseignant</th>
                                        <th>Statut</th>
                                        <th>Commentaire</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($presences as $presence): ?>
                                    <tr class="presence-row">
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($presence['date'])); ?>
                                        </td>
                                        <td>
                                            <?php 
                                            echo date('H:i', strtotime($presence['heure_debut'])) . ' - ' . 
                                                 date('H:i', strtotime($presence['heure_fin'])); 
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($presence['matiere_nom']); ?></td>
                                        <td><?php echo htmlspecialchars($presence['enseignant_nom']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusClass($presence['statut']); ?>">
                                                <?php echo ucfirst($presence['statut']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($presence['commentaire']): ?>
                                                <span class="text-muted">
                                                    <?php echo htmlspecialchars($presence['commentaire']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                        echo $matiere_id ? '&matiere=' . $matiere_id : ''; 
                                        echo $statut ? '&statut=' . $statut : '';
                                        echo $date_debut ? '&date_debut=' . $date_debut : '';
                                        echo $date_fin ? '&date_fin=' . $date_fin : '';
                                    ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
