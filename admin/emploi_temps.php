<?php
session_start();
require_once('../config/db.php');

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'ajouter':
                $enseignant_id = $_POST['enseignant_id'];
                $matiere_id = $_POST['matiere_id'];
                $classe_id = $_POST['classe_id'];
                $jour_semaine = $_POST['jour_semaine'];
                $heure_debut = $_POST['heure_debut'];
                $heure_fin = $_POST['heure_fin'];
                $salle = $_POST['salle'];
                $annee_scolaire = $_POST['annee_scolaire'];
                $semestre = $_POST['semestre'];

                // Vérifier si l'enseignant est disponible sur ce créneau
                $stmt = $conn->prepare("SELECT COUNT(*) FROM emploi_temps 
                    WHERE enseignant_id = ? 
                    AND jour_semaine = ? 
                    AND annee_scolaire = ? 
                    AND (
                        (heure_debut <= ? AND heure_fin > ?) OR
                        (heure_debut < ? AND heure_fin >= ?) OR
                        (heure_debut >= ? AND heure_fin <= ?)
                    )");
                $stmt->execute([
                    $enseignant_id, 
                    $jour_semaine, 
                    $annee_scolaire,
                    $heure_debut, $heure_debut,
                    $heure_fin, $heure_fin,
                    $heure_debut, $heure_fin
                ]);
                $conflit_enseignant = $stmt->fetchColumn() > 0;

                // Vérifier si la classe est disponible sur ce créneau
                $stmt = $conn->prepare("SELECT COUNT(*) FROM emploi_temps 
                    WHERE classe_id = ? 
                    AND jour_semaine = ? 
                    AND annee_scolaire = ? 
                    AND (
                        (heure_debut <= ? AND heure_fin > ?) OR
                        (heure_debut < ? AND heure_fin >= ?) OR
                        (heure_debut >= ? AND heure_fin <= ?)
                    )");
                $stmt->execute([
                    $classe_id, 
                    $jour_semaine, 
                    $annee_scolaire,
                    $heure_debut, $heure_debut,
                    $heure_fin, $heure_fin,
                    $heure_debut, $heure_fin
                ]);
                $conflit_classe = $stmt->fetchColumn() > 0;

                if ($conflit_enseignant) {
                    $_SESSION['error'] = "L'enseignant a déjà un cours sur ce créneau";
                } elseif ($conflit_classe) {
                    $_SESSION['error'] = "La classe a déjà un cours sur ce créneau";
                } else {
                    try {
                        $stmt = $conn->prepare("INSERT INTO emploi_temps (enseignant_id, matiere_id, classe_id, jour_semaine, heure_debut, heure_fin, salle, annee_scolaire, semestre) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$enseignant_id, $matiere_id, $classe_id, $jour_semaine, $heure_debut, $heure_fin, $salle, $annee_scolaire, $semestre]);
                        $_SESSION['success'] = "Cours ajouté avec succès";
                    } catch (PDOException $e) {
                        $_SESSION['error'] = "Erreur lors de l'ajout du cours";
                    }
                }
                break;

            case 'supprimer':
                $cours_id = $_POST['cours_id'];
                try {
                    $stmt = $conn->prepare("DELETE FROM emploi_temps WHERE id = ?");
                    $stmt->execute([$cours_id]);
                    $_SESSION['success'] = "Cours supprimé avec succès";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Erreur lors de la suppression du cours";
                }
                break;
        }
        header('Location: emploi_temps.php');
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

// Récupérer les emplois du temps
$stmt = $conn->query("
    SELECT e.*, 
           u.nom as enseignant_nom, u.prenom as enseignant_prenom,
           m.nom as matiere_nom,
           c.nom as classe_nom, c.niveau as classe_niveau
    FROM emploi_temps e
    JOIN users u ON e.enseignant_id = u.id
    JOIN matieres m ON e.matiere_id = m.id
    JOIN classes c ON e.classe_id = c.id
    ORDER BY e.jour_semaine, e.heure_debut, c.niveau, c.nom
");
$emplois = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour formater l'heure
function formatHeure($heure) {
    return date('H:i', strtotime($heure));
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emploi du Temps - Administration</title>
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
        .cours-card {
            border-left: 4px solid #764ba2;
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
                <a class="nav-link active" href="emploi_temps.php">
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
                <h4 class="mb-0">Emploi du Temps</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajoutCoursModal">
                    <i class="fas fa-plus me-2"></i>Nouveau Cours
                </button>
            </div>

            <!-- Liste des cours par jour -->
            <?php foreach ($jours as $jour_key => $jour_nom): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><?= $jour_nom ?></h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $cours_du_jour = array_filter($emplois, function($cours) use ($jour_key) {
                            return $cours['jour_semaine'] === $jour_key;
                        });
                        
                        if (empty($cours_du_jour)):
                        ?>
                            <p class="text-muted mb-0">Aucun cours programmé</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($cours_du_jour as $cours): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card cours-card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title"><?= htmlspecialchars($cours['matiere_nom']) ?></h6>
                                                <p class="card-text mb-1">
                                                    <i class="fas fa-user-tie me-2"></i>
                                                    <?= htmlspecialchars($cours['enseignant_prenom'] . ' ' . $cours['enseignant_nom']) ?>
                                                </p>
                                                <p class="card-text mb-1">
                                                    <i class="fas fa-users me-2"></i>
                                                    <?= htmlspecialchars($cours['classe_nom'] . ' (' . $cours['classe_niveau'] . ')') ?>
                                                </p>
                                                <p class="card-text mb-1">
                                                    <i class="fas fa-clock me-2"></i>
                                                    <?= formatHeure($cours['heure_debut']) ?> - <?= formatHeure($cours['heure_fin']) ?>
                                                </p>
                                                <?php if ($cours['salle']): ?>
                                                    <p class="card-text mb-1">
                                                        <i class="fas fa-door-open me-2"></i>
                                                        <?= htmlspecialchars($cours['salle']) ?>
                                                    </p>
                                                <?php endif; ?>
                                                <p class="card-text mb-0">
                                                    <i class="fas fa-calendar me-2"></i>
                                                    Semestre <?= $cours['semestre'] ?>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent border-0">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="supprimer">
                                                    <input type="hidden" name="cours_id" value="<?= $cours['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce cours ?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal Ajout Cours -->
    <div class="modal fade" id="ajoutCoursModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nouveau Cours</h5>
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
                            <label for="jour_semaine" class="form-label">Jour</label>
                            <select name="jour_semaine" id="jour_semaine" class="form-select" required>
                                <option value="">Sélectionner un jour</option>
                                <?php foreach ($jours as $jour_key => $jour_nom): ?>
                                    <option value="<?= $jour_key ?>"><?= $jour_nom ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="heure_debut" class="form-label">Heure de début</label>
                                <input type="time" name="heure_debut" id="heure_debut" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="heure_fin" class="form-label">Heure de fin</label>
                                <input type="time" name="heure_fin" id="heure_fin" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="salle" class="form-label">Salle</label>
                            <input type="text" name="salle" id="salle" class="form-control" placeholder="Ex: Salle 101">
                        </div>

                        <div class="mb-3">
                            <label for="annee_scolaire" class="form-label">Année scolaire</label>
                            <input type="text" name="annee_scolaire" id="annee_scolaire" class="form-control" value="2025-2026" required>
                        </div>

                        <div class="mb-3">
                            <label for="semestre" class="form-label">Semestre</label>
                            <select name="semestre" id="semestre" class="form-select" required>
                                <option value="">Sélectionner un semestre</option>
                                <option value="1">Semestre 1</option>
                                <option value="2">Semestre 2</option>
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
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Vérifier que l'heure de fin est après l'heure de début
        document.getElementById('heure_fin').addEventListener('change', function() {
            var heureDebut = document.getElementById('heure_debut').value;
            var heureFin = this.value;
            
            if (heureDebut && heureFin && heureDebut >= heureFin) {
                alert("L'heure de fin doit être après l'heure de début");
                this.value = '';
            }
        });

        document.getElementById('heure_debut').addEventListener('change', function() {
            var heureDebut = this.value;
            var heureFin = document.getElementById('heure_fin').value;
            
            if (heureDebut && heureFin && heureDebut >= heureFin) {
                alert("L'heure de fin doit être après l'heure de début");
                document.getElementById('heure_fin').value = '';
            }
        });
    });
    </script>
</body>
</html>
