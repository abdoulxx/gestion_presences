<?php
session_start();
require_once('../config/db.php');

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

$success = $error = '';

// Traitement de l'ajout/modification de classe
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $nom = trim($_POST['nom']);
        $niveau = trim($_POST['niveau']);
        $capacite = (int)$_POST['capacite'];

        if (empty($nom) || empty($niveau) || $capacite <= 0) {
            $error = "Tous les champs sont obligatoires et la capacité doit être supérieure à 0";
        } else {
            try {
                if ($_POST['action'] === 'add') {
                    $stmt = $conn->prepare("INSERT INTO classes (nom, niveau, capacite) VALUES (?, ?, ?)");
                    $stmt->execute([$nom, $niveau, $capacite]);
                    $success = "Classe ajoutée avec succès";
                } elseif ($_POST['action'] === 'edit') {
                    $id = (int)$_POST['classe_id'];
                    $stmt = $conn->prepare("UPDATE classes SET nom = ?, niveau = ?, capacite = ? WHERE id = ?");
                    $stmt->execute([$nom, $niveau, $capacite, $id]);
                    $success = "Classe modifiée avec succès";
                } elseif ($_POST['action'] === 'delete') {
                    $id = (int)$_POST['classe_id'];
                    // Vérifier si la classe a des étudiants
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM inscriptions WHERE classe_id = ?");
                    $stmt->execute([$id]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception("Impossible de supprimer cette classe car elle contient des étudiants");
                    }
                    $stmt = $conn->prepare("DELETE FROM classes WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = "Classe supprimée avec succès";
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// Récupérer la liste des classes avec le nombre d'étudiants
$stmt = $conn->query("
    SELECT c.*, COUNT(i.etudiant_id) as nb_etudiants 
    FROM classes c 
    LEFT JOIN inscriptions i ON c.id = i.classe_id 
    GROUP BY c.id 
    ORDER BY c.niveau, c.nom
");
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Classes - Administration</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #764ba2;
            --secondary-color: #667eea;
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

        .card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1rem 1.5rem;
        }

        .btn-custom {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .btn-custom:hover {
            background: var(--secondary-color);
            color: white;
        }

        .table th {
            background-color: rgba(118, 75, 162, 0.1);
        }

        .classe-card {
            transition: transform 0.3s ease;
        }

        .classe-card:hover {
            transform: translateY(-5px);
        }

        .progress {
            height: 10px;
            border-radius: 5px;
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
                <a class="nav-link active" href="classes.php">
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
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Gestion des Classes</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item active">Classes</li>
                    </ol>
                </nav>
            </div>
            <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#addClasseModal">
                <i class="fas fa-plus me-2"></i>Nouvelle Classe
            </button>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success fade-in" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger fade-in" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Classes Grid -->
        <div class="row g-4">
            <?php foreach ($classes as $classe): ?>
            <div class="col-md-4">
                <div class="card classe-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">
                                <?php echo htmlspecialchars($classe['nom']); ?>
                            </h5>
                            <span class="badge bg-primary">
                                <?php echo htmlspecialchars($classe['niveau']); ?>
                            </span>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Capacité:</small>
                            <div class="progress mt-1">
                                <?php 
                                $occupation = ($classe['nb_etudiants'] / $classe['capacite']) * 100;
                                $progressClass = $occupation >= 90 ? 'bg-danger' : ($occupation >= 70 ? 'bg-warning' : 'bg-success');
                                ?>
                                <div class="progress-bar <?php echo $progressClass; ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo $occupation; ?>%"
                                     aria-valuenow="<?php echo $occupation; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                            <small class="text-muted">
                                <?php echo $classe['nb_etudiants']; ?> / <?php echo $classe['capacite']; ?> étudiants
                            </small>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="editClasse(<?php echo htmlspecialchars(json_encode($classe)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteClasse(<?php echo $classe['id']; ?>, '<?php echo htmlspecialchars($classe['nom']); ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal Ajout Classe -->
    <div class="modal fade" id="addClasseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nouvelle Classe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom de la classe</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                        <div class="mb-3">
                            <label for="niveau" class="form-label">Niveau</label>
                            <select class="form-select" id="niveau" name="niveau" required>
                                <option value="">Sélectionner un niveau</option>
                                <option value="licence1">licence1</option>
                                <option value="licence2">licence2</option>
                                <option value="licence3">licence3</option>
                                <option value="master1">master1</option>
                                <option value="master2">master2</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="capacite" class="form-label">Capacité</label>
                            <input type="number" class="form-control" id="capacite" name="capacite" 
                                   min="1" max="50" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-custom">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Modification Classe -->
    <div class="modal fade" id="editClasseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier la Classe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="classe_id" id="edit_classe_id">
                        <div class="mb-3">
                            <label for="edit_nom" class="form-label">Nom de la classe</label>
                            <input type="text" class="form-control" id="edit_nom" name="nom" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_niveau" class="form-label">Niveau</label>
                            <select class="form-select" id="edit_niveau" name="niveau" required>
                                <option value="">Sélectionner un niveau</option>
                                <option value="licence1">licence1</option>
                                <option value="licence2">licence2</option>
                                <option value="licence3">licence3</option>
                                <option value="master1">master1</option>
                                <option value="master2">master2</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_capacite" class="form-label">Capacité</label>
                            <input type="number" class="form-control" id="edit_capacite" name="capacite" 
                                   min="1" max="50" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-custom">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Suppression -->
    <div class="modal fade" id="deleteClasseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer la classe <strong id="delete_classe_nom"></strong> ?</p>
                    <p class="text-danger">Cette action est irréversible.</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="classe_id" id="delete_classe_id">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editClasse(classe) {
            document.getElementById('edit_classe_id').value = classe.id;
            document.getElementById('edit_nom').value = classe.nom;
            document.getElementById('edit_niveau').value = classe.niveau;
            document.getElementById('edit_capacite').value = classe.capacite;
            new bootstrap.Modal(document.getElementById('editClasseModal')).show();
        }

        function deleteClasse(id, nom) {
            document.getElementById('delete_classe_id').value = id;
            document.getElementById('delete_classe_nom').textContent = nom;
            new bootstrap.Modal(document.getElementById('deleteClasseModal')).show();
        }
    </script>
</body>
</html>
