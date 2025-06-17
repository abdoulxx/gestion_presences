<?php
session_start();
require_once('../config/db.php');

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

// Traitement du formulaire d'attribution
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'ajouter':
                $enseignant_id = $_POST['enseignant_id'];
                $matiere_id = $_POST['matiere_id'];
                $classe_id = $_POST['classe_id'];
                $annee_scolaire = $_POST['annee_scolaire'];

                try {
                    $stmt = $conn->prepare("INSERT INTO attributions_enseignants (enseignant_id, matiere_id, classe_id, annee_scolaire) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$enseignant_id, $matiere_id, $classe_id, $annee_scolaire]);
                    $_SESSION['success'] = "Attribution ajoutée avec succès";
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) { // Code d'erreur pour duplicate entry
                        $_SESSION['error'] = "Cette attribution existe déjà";
                    } else {
                        $_SESSION['error'] = "Erreur lors de l'ajout de l'attribution";
                    }
                }
                break;

            case 'supprimer':
                $attribution_id = $_POST['attribution_id'];
                try {
                    $stmt = $conn->prepare("DELETE FROM attributions_enseignants WHERE id = ?");
                    $stmt->execute([$attribution_id]);
                    $_SESSION['success'] = "Attribution supprimée avec succès";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Erreur lors de la suppression de l'attribution";
                }
                break;
        }
        header('Location: attributions.php');
        exit();
    }
}

// Récupérer les enseignants
$stmt = $conn->query("SELECT id, nom, prenom FROM users WHERE role = 'enseignant' ORDER BY nom, prenom");
$enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les matières
$stmt = $conn->query("SELECT id, nom FROM matieres ORDER BY nom");
$matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les classes
$stmt = $conn->query("SELECT id, nom, niveau FROM classes ORDER BY niveau, nom");
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les attributions existantes
$stmt = $conn->query("
    SELECT a.*, 
           u.nom as enseignant_nom, u.prenom as enseignant_prenom,
           m.nom as matiere_nom,
           c.nom as classe_nom, c.niveau as classe_niveau
    FROM attributions_enseignants a
    JOIN users u ON a.enseignant_id = u.id
    JOIN matieres m ON a.matiere_id = m.id
    JOIN classes c ON a.classe_id = c.id
    ORDER BY u.nom, u.prenom, c.niveau, c.nom, m.nom
");
$attributions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Attributions - Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            background: linear-gradient(180deg, #764ba2 0%, #667eea 100%);
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
                <a class="nav-link " href="dashboard.php">
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
                <a class="nav-link active " href="attributions.php">
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
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Gestion des Attributions</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajoutAttributionModal">
                    <i class="fas fa-plus me-2"></i>Nouvelle Attribution
                </button>
            </div>

            <!-- Liste des attributions -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Enseignant</th>
                                    <th>Matière</th>
                                    <th>Classe</th>
                                    <th>Année Scolaire</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($attributions)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">Aucune attribution trouvée</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($attributions as $attribution): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($attribution['enseignant_prenom'] . ' ' . $attribution['enseignant_nom']) ?></td>
                                        <td><?= htmlspecialchars($attribution['matiere_nom']) ?></td>
                                        <td><?= htmlspecialchars($attribution['classe_nom'] . ' (' . $attribution['classe_niveau'] . ')') ?></td>
                                        <td><?= htmlspecialchars($attribution['annee_scolaire']) ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="supprimer">
                                                <input type="hidden" name="attribution_id" value="<?= $attribution['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette attribution ?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ajout Attribution -->
    <div class="modal fade" id="ajoutAttributionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nouvelle Attribution</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="ajouter">
                        
                        <div class="mb-3">
                            <label for="enseignant_id" class="form-label">Enseignant</label>
                            <select name="enseignant_id" id="enseignant_id" class="form-select" required>
                                <option value="">Sélectionner un enseignant</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?= $enseignant['id'] ?>">
                                        <?= htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="matiere_id" class="form-label">Matière</label>
                            <select name="matiere_id" id="matiere_id" class="form-select" required>
                                <option value="">Sélectionner une matière</option>
                                <?php foreach ($matieres as $matiere): ?>
                                    <option value="<?= $matiere['id'] ?>">
                                        <?= htmlspecialchars($matiere['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="classe_id" class="form-label">Classe</label>
                            <select name="classe_id" id="classe_id" class="form-select" required>
                                <option value="">Sélectionner une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?= $classe['id'] ?>">
                                        <?= htmlspecialchars($classe['nom'] . ' (' . $classe['niveau'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="annee_scolaire" class="form-label">Année Scolaire</label>
                            <select name="annee_scolaire" id="annee_scolaire" class="form-select" required>
                                <?php
                                $annee_actuelle = date('Y');
                                for ($i = 0; $i < 2; $i++) {
                                    $annee = $annee_actuelle + $i;
                                    $annee_suivante = $annee + 1;
                                    $annee_scolaire = $annee . '-' . $annee_suivante;
                                    echo "<option value=\"$annee_scolaire\">$annee_scolaire</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Message de succès/erreur -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3" role="alert">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show position-fixed top-0 end-0 m-3" role="alert">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
