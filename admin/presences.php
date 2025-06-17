<?php
session_start();
require_once('../config/db.php');

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

// Définir la date par défaut
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'ajouter':
                $cours_id = $_POST['cours_id'];
                $etudiant_ids = $_POST['etudiant_ids'];
                $statuts = $_POST['statuts'];
                $date = $_POST['date'];
                $commentaires = $_POST['commentaires'] ?? [];

                try {
                    $conn->beginTransaction();

                    $stmt = $conn->prepare("INSERT INTO presences (cours_id, etudiant_id, date, statut, commentaire) VALUES (?, ?, ?, ?, ?)");
                    
                    foreach ($etudiant_ids as $index => $etudiant_id) {
                        $stmt->execute([
                            $cours_id,
                            $etudiant_id,
                            $date,
                            $statuts[$index],
                            $commentaires[$index] ?? null
                        ]);
                    }

                    $conn->commit();
                    $_SESSION['success'] = "Présences enregistrées avec succès";
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $_SESSION['error'] = "Erreur lors de l'enregistrement des présences";
                }
                break;

            case 'modifier':
                $presence_id = $_POST['presence_id'];
                $statut = $_POST['statut'];
                $commentaire = $_POST['commentaire'];

                try {
                    $stmt = $conn->prepare("UPDATE presences SET statut = ?, commentaire = ? WHERE id = ?");
                    $stmt->execute([$statut, $commentaire, $presence_id]);
                    $_SESSION['success'] = "Présence modifiée avec succès";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Erreur lors de la modification de la présence";
                }
                break;

            case 'supprimer':
                $presence_id = $_POST['presence_id'];
                try {
                    $stmt = $conn->prepare("DELETE FROM presences WHERE id = ?");
                    $stmt->execute([$presence_id]);
                    $_SESSION['success'] = "Présence supprimée avec succès";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Erreur lors de la suppression de la présence";
                }
                break;
        }
        header('Location: presences.php' . (isset($_GET['date']) ? '?date=' . $_GET['date'] : ''));
        exit();
    }
}

// Récupérer les cours pour le sélecteur
$stmt = $conn->prepare("
    SELECT et.id, et.heure_debut, et.heure_fin, 
           m.nom as matiere_nom,
           c.nom as classe_nom, c.niveau as classe_niveau,
           CONCAT(u.prenom, ' ', u.nom) as enseignant_nom
    FROM emploi_temps et
    JOIN matieres m ON et.matiere_id = m.id
    JOIN classes c ON et.classe_id = c.id
    JOIN users u ON et.enseignant_id = u.id
    WHERE et.jour_semaine = LOWER(DATE_FORMAT(:date, '%W'))
    ORDER BY et.heure_debut
");
$stmt->execute([':date' => $date]);
$cours_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Définir la date par défaut
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Récupérer les classes pour le filtre
$stmt = $conn->query("SELECT id, nom, niveau FROM classes ORDER BY niveau, nom");
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtres
$classe_id = isset($_GET['classe_id']) ? $_GET['classe_id'] : '';

// Récupérer les présences
$query = "
    SELECT p.*, 
           e.nom as etudiant_nom, e.prenom as etudiant_prenom,
           m.nom as matiere_nom,
           c.nom as classe_nom, c.niveau as classe_niveau,
           CONCAT(u.prenom, ' ', u.nom) as enseignant_nom,
           et.heure_debut, et.heure_fin
    FROM presences p
    JOIN users e ON p.etudiant_id = e.id
    JOIN emploi_temps et ON p.cours_id = et.id
    JOIN users u ON et.enseignant_id = u.id
    JOIN matieres m ON et.matiere_id = m.id
    JOIN classes c ON et.classe_id = c.id
    WHERE DATE(p.created_at) = :date
";

$params = [':date' => $date];

if ($classe_id) {
    $query .= " AND et.classe_id = :classe_id";
    $params[':classe_id'] = $classe_id;
}

$query .= " ORDER BY et.heure_debut, e.nom, e.prenom";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$presences = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour formater l'heure
function formatHeure($heure) {
    return date('H:i', strtotime($heure));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Présences - Administration</title>
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
        .presence-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .presence-present {
            background-color: #28a745;
        }
        .presence-absent {
            background-color: #dc3545;
        }
        .presence-retard {
            background-color: #ffc107;
        }
        .presence-excuse {
            background-color: #17a2b8;
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
                <a class="nav-link" href="emploi_temps.php">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Emploi du temps
                </a>
                <a class="nav-link active" href="presences.php">
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
                <h4 class="mb-0">Gestion des Présences</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajoutPresenceModal">
                    <i class="fas fa-plus me-2"></i>Marquer Présence
                </button>
            </div>

            <!-- Filtres -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="classe_id" class="form-label">Classe</label>
                            <select name="classe_id" id="classe_id" class="form-select">
                                <option value="">Toutes les classes</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?= $classe['id'] ?>" <?= $classe_id == $classe['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($classe['nom'] . ' (' . $classe['niveau'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" name="date" id="date" class="form-control" value="<?= $date ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block">Filtrer</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Liste des présences -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Étudiant</th>
                                    <th>Classe</th>
                                    <th>Matière</th>
                                    <th>Enseignant</th>
                                    <th>Horaire</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($presences)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">Aucune présence enregistrée pour cette date</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($presences as $presence): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($presence['etudiant_prenom'] . ' ' . $presence['etudiant_nom']) ?></td>
                                        <td><?= htmlspecialchars($presence['classe_nom'] . ' (' . $presence['classe_niveau'] . ')') ?></td>
                                        <td><?= htmlspecialchars($presence['matiere_nom']) ?></td>
                                        <td><?= htmlspecialchars($presence['enseignant_nom']) ?></td>
                                        <td><?= formatHeure($presence['heure_debut']) ?> - <?= formatHeure($presence['heure_fin']) ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch ($presence['statut']) {
                                                case 'present':
                                                    $status_class = 'presence-present';
                                                    $status_text = 'Présent';
                                                    break;
                                                case 'absent':
                                                    $status_class = 'presence-absent';
                                                    $status_text = 'Absent';
                                                    break;
                                                case 'retard':
                                                    $status_class = 'presence-retard';
                                                    $status_text = 'En retard';
                                                    break;
                                                case 'excuse':
                                                    $status_class = 'presence-excuse';
                                                    $status_text = 'Excusé';
                                                    break;
                                            }
                                            ?>
                                            <span class="presence-status <?= $status_class ?>"></span>
                                            <?= $status_text ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary me-1" onclick="modifierPresence(<?= $presence['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="supprimer">
                                                <input type="hidden" name="presence_id" value="<?= $presence['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette présence ?')">
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

    <!-- Modal Ajout Présence -->
    <div class="modal fade" id="ajoutPresenceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Marquer les Présences</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formPresence">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="ajouter">
                        
                        <div class="mb-3">
                            <label for="date_presence" class="form-label">Date</label>
                            <input type="date" name="date" id="date_presence" class="form-control" required value="<?= $date ?>">
                        </div>

                        <div class="mb-3">
                            <label for="cours_id" class="form-label">Cours</label>
                            <select name="cours_id" id="cours_id" class="form-select" required>
                                <option value="">Sélectionner un cours</option>
                                <?php foreach ($cours_disponibles as $cours): ?>
                                    <option value="<?= $cours['id'] ?>">
                                        <?= htmlspecialchars($cours['matiere_nom'] . ' - ' . 
                                            $cours['classe_nom'] . ' (' . $cours['classe_niveau'] . ') - ' .
                                            formatHeure($cours['heure_debut']) . '-' . formatHeure($cours['heure_fin']) . ' - ' .
                                            $cours['enseignant_nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="liste_etudiants" class="d-none">
                            <h6 class="mb-3">Liste des étudiants</h6>
                            <div id="etudiants_container">
                                <!-- La liste des étudiants sera chargée ici dynamiquement -->
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Modification Présence -->
    <div class="modal fade" id="modifierPresenceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier la Présence</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="modifier">
                        <input type="hidden" name="presence_id" id="presence_id_modifier">

                        <div class="mb-3">
                            <label for="statut_modifier" class="form-label">Statut</label>
                            <select name="statut" id="statut_modifier" class="form-select" required>
                                <option value="present">Présent</option>
                                <option value="absent">Absent</option>
                                <option value="retard">En retard</option>
                                <option value="excuse">Excusé</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="commentaire_modifier" class="form-label">Commentaire</label>
                            <textarea name="commentaire" id="commentaire_modifier" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
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
        // Charger les étudiants quand un cours est sélectionné
        document.getElementById('cours_id').addEventListener('change', function() {
            const coursId = this.value;
            const date = document.getElementById('date_presence').value;
            if (coursId && date) {
                fetch(`get_etudiants.php?cours_id=${coursId}&date=${date}`)
                    .then(response => response.json())
                    .then(data => {
                        const container = document.getElementById('etudiants_container');
                        const listeEtudiants = document.getElementById('liste_etudiants');
                        
                        if (data.error) {
                            alert(data.error);
                            return;
                        }

                        if (data.length === 0) {
                            container.innerHTML = '<div class="alert alert-info">Tous les étudiants ont déjà été marqués pour ce cours.</div>';
                        } else {
                            container.innerHTML = data.map((etudiant, index) => `
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">${etudiant.prenom} ${etudiant.nom}</h6>
                                        <input type="hidden" name="etudiant_ids[]" value="${etudiant.id}">
                                        <div class="mb-2">
                                            <select name="statuts[]" class="form-select" required>
                                                <option value="present">Présent</option>
                                                <option value="absent">Absent</option>
                                                <option value="retard">En retard</option>
                                                <option value="excuse">Excusé</option>
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <textarea name="commentaires[]" class="form-control" rows="2" placeholder="Commentaire (optionnel)"></textarea>
                                        </div>
                                    </div>
                                </div>
                            `).join('');
                        }
                        
                        listeEtudiants.classList.remove('d-none');
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Erreur lors du chargement des étudiants');
                    });
            }
        });

        // Recharger les étudiants si la date change
        document.getElementById('date_presence').addEventListener('change', function() {
            const coursSelect = document.getElementById('cours_id');
            if (coursSelect.value) {
                coursSelect.dispatchEvent(new Event('change'));
            }
        });
    });

    // Fonction pour ouvrir la modale de modification
    function modifierPresence(presenceId, statut = null, commentaire = '') {
        document.getElementById('presence_id_modifier').value = presenceId;
        if (statut) {
            document.getElementById('statut_modifier').value = statut;
        }
        document.getElementById('commentaire_modifier').value = commentaire;
        
        new bootstrap.Modal(document.getElementById('modifierPresenceModal')).show();
    }
    </script>
</body>
</html>
