<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'enseignant') {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['cours_id'])) {
    header('Location: gestion_presences.php');
    exit();
}

$cours_id = $_GET['cours_id'];

// Récupérer les informations du cours
$stmt = $conn->prepare("
    SELECT 
        e.*,
        m.nom as matiere_nom,
        c.nom as classe_nom,
        c.niveau as classe_niveau,
        c.id as classe_id
    FROM emploi_temps e
    JOIN matieres m ON e.matiere_id = m.id
    JOIN classes c ON e.classe_id = c.id
    WHERE e.id = ? AND e.enseignant_id = ?");
$stmt->execute([$cours_id, $_SESSION['user_id']]);
$cours = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cours) {
    header('Location: gestion_presences.php');
    exit();
}

// Traitement de la modification de l'appel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        error_log("Début de la vérification des données");
        
        // Vérification des données requises
        if (!isset($_POST['date_appel'])) {
            throw new Exception("La date d'appel est manquante");
        }
        if (!isset($_POST['presence']) || !is_array($_POST['presence'])) {
            throw new Exception("Les données de présence sont manquantes ou invalides");
        }
        
        error_log("Date d'appel: " . $_POST['date_appel']);
        error_log("Nombre d'étudiants: " . count($_POST['presence']));
        error_log("Données POST: " . print_r($_POST, true));
        
        $conn->beginTransaction();

        foreach ($_POST['presence'] as $etudiant_id => $statut) {
            if (!in_array($statut, ['present', 'absent', 'retard'])) {
                error_log("Statut invalide pour etudiant_id $etudiant_id: $statut");
                throw new Exception("Statut de présence invalide");
            }
            
            // Vérifier si l'enregistrement existe
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM presences 
                WHERE cours_id = ? 
                AND etudiant_id = ? 
                AND date = ?");
            $stmt->execute([$cours_id, $etudiant_id, $_POST['date_appel']]);
            $exists = $stmt->fetchColumn() > 0;
            
            error_log("Vérification existence pour etudiant_id $etudiant_id: " . ($exists ? 'existe' : 'nouveau'));
            
            $commentaire = isset($_POST['commentaire'][$etudiant_id]) ? $_POST['commentaire'][$etudiant_id] : null;
            
            if ($exists) {
                error_log("Mise à jour présence pour etudiant_id: $etudiant_id, statut: $statut");
                // Mise à jour si existe
                $stmt = $conn->prepare("
                    UPDATE presences 
                    SET statut = ?, 
                        commentaire = ?
                    WHERE cours_id = ? 
                    AND etudiant_id = ? 
                    AND date = ?");
                $result = $stmt->execute([$statut, $commentaire, $cours_id, $etudiant_id, $_POST['date_appel']]);
                if (!$result) {
                    error_log("Erreur SQL update: " . implode(", ", $stmt->errorInfo()));
                }
            } else {
                error_log("Nouvelle présence pour etudiant_id: $etudiant_id, statut: $statut");
                // Insertion si n'existe pas
                $stmt = $conn->prepare("
                    INSERT INTO presences 
                    (cours_id, etudiant_id, date, statut, commentaire)
                    VALUES (?, ?, ?, ?, ?)");
                $result = $stmt->execute([$cours_id, $etudiant_id, $_POST['date_appel'], $statut, $commentaire]);
                if (!$result) {
                    error_log("Erreur SQL insert: " . implode(", ", $stmt->errorInfo()));
                }
            }
        }

        $conn->commit();
        $_SESSION['success_message'] = "Les modifications ont été enregistrées avec succès.";
        header('Location: ' . $_SERVER['PHP_SELF'] . '?cours_id=' . $cours_id);
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Erreur lors de la modification de l'appel: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        $error_message = "Une erreur est survenue lors de la modification de l'appel: " . $e->getMessage();
    }
}

// Récupérer la dernière date d'appel pour ce cours ou utiliser la date du jour
$stmt = $conn->prepare("
    SELECT DISTINCT date 
    FROM presences 
    WHERE cours_id = ? 
    ORDER BY date DESC 
    LIMIT 1");
$stmt->execute([$cours_id]);
$date_appel = $stmt->fetchColumn() ?: date('Y-m-d');

// Récupérer la liste des présences
$stmt = $conn->prepare("
    SELECT 
        p.*,
        u.id as etudiant_id,
        u.nom,
        u.prenom
    FROM users u
    JOIN inscriptions i ON u.id = i.etudiant_id
    LEFT JOIN presences p ON u.id = p.etudiant_id 
        AND p.cours_id = ? 
        AND p.date = ?
    WHERE i.classe_id = ? 
    AND u.role = 'etudiant'
    ORDER BY u.nom, u.prenom");
$stmt->execute([$cours_id, $date_appel, $cours['classe_id']]);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voir/Modifier l'appel - Enseignant</title>
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

        .presence-table th {
            background-color: var(--primary-color);
            color: white;
        }

        .presence-options label {
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            transition: all 0.2s;
        }

        .presence-options input[type="radio"] {
            display: none;
        }

        .presence-options input[type="radio"]:checked + label {
            background-color: var(--primary-color);
            color: white;
        }

        .presence-options label:hover {
            background-color: #e9ecef;
        }

        .presence-options input[type="radio"]:checked + label:hover {
            background-color: var(--secondary-color);
        }

        .commentaire-toggle {
            cursor: pointer;
            color: var(--primary-color);
        }

        .commentaire-toggle:hover {
            color: var(--secondary-color);
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
                <a class="nav-link" href="emploi_temps.php">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Emploi du temps
                </a>
                <a class="nav-link active" href="gestion_presences.php">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Gestion présences
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
                    <h4 class="mb-0">Modifier l'appel</h4>
                    <p class="text-muted mb-0">
                        <?= htmlspecialchars($cours['matiere_nom']) ?> - 
                        <?= htmlspecialchars($cours['classe_nom']) ?> 
                        (<?= htmlspecialchars($cours['classe_niveau']) ?>)
                    </p>
                </div>
                <div class="text-end">
                    <p class="mb-0"><?= date('d/m/Y', strtotime($date_appel)) ?></p>
                    <p class="text-muted mb-0">
                        <?= substr($cours['heure_debut'], 0, 5) ?> - 
                        <?= substr($cours['heure_fin'], 0, 5) ?>
                    </p>
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= $_SESSION['success_message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Formulaire de modification -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="date_appel" value="<?php echo $date_appel; ?>">
                        <div class="table-responsive">
                            <table class="table table-hover presence-table">
                                <thead>
                                    <tr>
                                        <th>Étudiant</th>
                                        <th class="text-center">Statut</th>
                                        <th>Commentaire</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($etudiants as $etudiant): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($etudiant['nom']) ?> 
                                                <?= htmlspecialchars($etudiant['prenom']) ?>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center gap-3 presence-options">
                                                    <div>
                                                        <input type="radio" 
                                                               name="presence[<?= $etudiant['etudiant_id'] ?>]" 
                                                               value="present" 
                                                               id="present_<?= $etudiant['etudiant_id'] ?>" 
                                                               <?= ($etudiant['statut'] ?? '') === 'present' ? 'checked' : '' ?>>
                                                        <label for="present_<?= $etudiant['etudiant_id'] ?>" 
                                                               class="mb-0 text-success">
                                                            <i class="fas fa-check me-1"></i>
                                                            Présent
                                                        </label>
                                                    </div>
                                                    <div>
                                                        <input type="radio" 
                                                               name="presence[<?= $etudiant['etudiant_id'] ?>]" 
                                                               value="absent" 
                                                               id="absent_<?= $etudiant['etudiant_id'] ?>"
                                                               <?= ($etudiant['statut'] ?? '') === 'absent' ? 'checked' : '' ?>>
                                                        <label for="absent_<?= $etudiant['etudiant_id'] ?>" 
                                                               class="mb-0 text-danger">
                                                            <i class="fas fa-times me-1"></i>
                                                            Absent
                                                        </label>
                                                    </div>
                                                    <div>
                                                        <input type="radio" 
                                                               name="presence[<?= $etudiant['etudiant_id'] ?>]" 
                                                               value="retard" 
                                                               id="retard_<?= $etudiant['etudiant_id'] ?>"
                                                               <?= ($etudiant['statut'] ?? '') === 'retard' ? 'checked' : '' ?>>
                                                        <label for="retard_<?= $etudiant['etudiant_id'] ?>" 
                                                               class="mb-0 text-warning">
                                                            <i class="fas fa-clock me-1"></i>
                                                            Retard
                                                        </label>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="input-group">
                                                    <input type="text" 
                                                           class="form-control form-control-sm"
                                                           name="commentaire[<?= $etudiant['etudiant_id'] ?>]"
                                                           value="<?= htmlspecialchars($etudiant['commentaire'] ?? '') ?>"
                                                           placeholder="Ajouter un commentaire...">
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="gestion_presences.php" class="btn btn-light">
                                <i class="fas fa-arrow-left me-1"></i>
                                Retour
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
