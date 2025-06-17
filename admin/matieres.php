<?php
session_start();
require_once('../config/db.php');

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

$success = $error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';

        // Vérifier les champs obligatoires seulement pour l'ajout et la modification
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            if (empty($nom)) {
                $error = "Le nom de la matière est obligatoire";
            }
        }
        
        if (empty($error)) {
            try {
                if ($_POST['action'] === 'add') {
                    // Vérifier si la matière existe déjà
                    $stmt = $conn->prepare("SELECT id FROM matieres WHERE nom = ?");
                    $stmt->execute([$nom]);
                    if ($stmt->rowCount() > 0) {
                        throw new Exception("Cette matière existe déjà");
                    }

                    // Créer la matière
                    $stmt = $conn->prepare("INSERT INTO matieres (nom, description) VALUES (?, ?)");
                    $stmt->execute([$nom, $description]);
                    $success = "Matière ajoutée avec succès";

                } elseif ($_POST['action'] === 'edit') {
                    $id = (int)$_POST['matiere_id'];
                    
                    // Vérifier si le nom existe déjà pour une autre matière
                    $stmt = $conn->prepare("SELECT id FROM matieres WHERE nom = ? AND id != ?");
                    $stmt->execute([$nom, $id]);
                    if ($stmt->rowCount() > 0) {
                        throw new Exception("Cette matière existe déjà");
                    }

                    // Mettre à jour la matière
                    $stmt = $conn->prepare("UPDATE matieres SET nom = ?, description = ? WHERE id = ?");
                    $stmt->execute([$nom, $description, $id]);
                    $success = "Matière modifiée avec succès";

                } elseif ($_POST['action'] === 'delete') {
                    $id = (int)$_POST['matiere_id'];

                    // Vérifier si la matière est utilisée dans des cours
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM cours WHERE matiere_id = ?");
                    $stmt->execute([$id]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception("Impossible de supprimer cette matière car elle est utilisée dans des cours");
                    }

                    // Supprimer la matière
                    $stmt = $conn->prepare("DELETE FROM matieres WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = "Matière supprimée avec succès";
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// Récupérer la liste des matières
$stmt = $conn->query("SELECT * FROM matieres ORDER BY nom");
$matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Matières - Administration</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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

        .table th {
            background-color: rgba(118, 75, 162, 0.1);
        }

        .description-cell {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
                <a class="nav-link active" href="matieres.php">
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
                <h4 class="mb-1">Gestion des Matières</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item active">Matières</li>
                    </ol>
                </nav>
            </div>
            <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#addMatiereModal">
                <i class="fas fa-plus me-2"></i>Nouvelle Matière
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

        <!-- Liste des matières -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Liste des Matières</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="matieresTable">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matieres as $matiere): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($matiere['nom']); ?></td>
                                <td class="description-cell" title="<?php echo htmlspecialchars($matiere['description']); ?>">
                                    <?php echo htmlspecialchars($matiere['description'] ?: 'Aucune description'); ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" 
                                            onclick="editMatiere(<?php echo htmlspecialchars(json_encode($matiere)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteMatiere(<?php echo $matiere['id']; ?>, '<?php echo htmlspecialchars($matiere['nom']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ajout Matière -->
    <div class="modal fade" id="addMatiereModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nouvelle Matière</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
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

    <!-- Modal Modification Matière -->
    <div class="modal fade" id="editMatiereModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier la Matière</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="matiere_id" id="edit_matiere_id">
                        <div class="mb-3">
                            <label for="edit_nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="edit_nom" name="nom" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
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
    <div class="modal fade" id="deleteMatiereModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer la matière <strong id="delete_matiere_nom"></strong> ?</p>
                    <p class="text-danger">Cette action est irréversible.</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="matiere_id" id="delete_matiere_id">
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
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialisation de DataTables
        $(document).ready(function() {
            $('#matieresTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json'
                }
            });

            // Activer les tooltips pour les descriptions tronquées
            $('[title]').tooltip();
        });

        function editMatiere(matiere) {
            document.getElementById('edit_matiere_id').value = matiere.id;
            document.getElementById('edit_nom').value = matiere.nom;
            document.getElementById('edit_description').value = matiere.description;
            new bootstrap.Modal(document.getElementById('editMatiereModal')).show();
        }

        function deleteMatiere(id, nom) {
            document.getElementById('delete_matiere_id').value = id;
            document.getElementById('delete_matiere_nom').textContent = nom;
            new bootstrap.Modal(document.getElementById('deleteMatiereModal')).show();
        }
    </script>
</body>
</html>
