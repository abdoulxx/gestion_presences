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
        $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $classe_id = isset($_POST['classe_id']) ? (int)$_POST['classe_id'] : 0;

        // Vérifier les champs obligatoires seulement pour l'ajout et la modification
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            if (empty($nom) || empty($prenom) || empty($email) || $classe_id <= 0) {
                $error = "Tous les champs sont obligatoires";
            }
        }
        
        if (empty($error)) {
            try {
                $conn->beginTransaction();

                if ($_POST['action'] === 'add') {
                    // Vérifier si l'email existe déjà
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->rowCount() > 0) {
                        throw new Exception("Cet email est déjà utilisé");
                    }

                    // Générer un mot de passe aléatoire
                    $password = bin2hex(random_bytes(4)); // 8 caractères
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Créer l'utilisateur
                    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, nom, prenom) VALUES (?, ?, ?, 'etudiant', ?, ?)");
                    $username = strtolower($prenom[0] . $nom); // première lettre du prénom + nom
                    $stmt->execute([$username, $hashed_password, $email, $nom, $prenom]);
                    $user_id = $conn->lastInsertId();

                    // Générer un matricule unique (année en cours + 4 chiffres aléatoires)
                    $annee = date('Y');
                    do {
                        $matricule = $annee . str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
                        $stmt = $conn->prepare("SELECT matricule FROM inscriptions WHERE matricule = ?");
                        $stmt->execute([$matricule]);
                    } while ($stmt->rowCount() > 0);

                    // Créer l'inscription
                    $stmt = $conn->prepare("INSERT INTO inscriptions (etudiant_id, classe_id, matricule, date_inscription) VALUES (?, ?, ?, CURDATE())");
                    $stmt->execute([$user_id, $classe_id, $matricule]);

                    $conn->commit();
                    $success = "Étudiant ajouté avec succès. Matricule: $matricule, Mot de passe temporaire: $password";

                } elseif ($_POST['action'] === 'edit') {
                    $id = (int)$_POST['etudiant_id'];
                    
                    // Vérifier si l'email existe déjà pour un autre utilisateur
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $id]);
                    if ($stmt->rowCount() > 0) {
                        throw new Exception("Cet email est déjà utilisé");
                    }

                    // Mettre à jour l'utilisateur
                    $stmt = $conn->prepare("UPDATE users SET email = ?, nom = ?, prenom = ? WHERE id = ?");
                    $stmt->execute([$email, $nom, $prenom, $id]);

                    // Mettre à jour l'inscription
                    $stmt = $conn->prepare("UPDATE inscriptions SET classe_id = ? WHERE etudiant_id = ?");
                    $stmt->execute([$classe_id, $id]);

                    $conn->commit();
                    $success = "Informations de l'étudiant mises à jour";

                } elseif ($_POST['action'] === 'delete') {
                    $id = (int)$_POST['etudiant_id'];

                    // Supprimer les inscriptions
                    $stmt = $conn->prepare("DELETE FROM inscriptions WHERE etudiant_id = ?");
                    $stmt->execute([$id]);

                    // Supprimer l'utilisateur
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$id]);

                    $conn->commit();
                    $success = "Étudiant supprimé avec succès";
                }
            } catch (Exception $e) {
                $conn->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

// Récupérer la liste des classes
$stmt = $conn->query("SELECT id, nom, niveau FROM classes ORDER BY niveau, nom");
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des étudiants avec leurs classes
$stmt = $conn->query("
    SELECT u.*, i.matricule, i.classe_id, c.nom as classe_nom, c.niveau as classe_niveau
    FROM users u
    JOIN inscriptions i ON u.id = i.etudiant_id
    JOIN classes c ON i.classe_id = c.id
    WHERE u.role = 'etudiant'
    ORDER BY u.nom, u.prenom
");
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Étudiants - Administration</title>
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

        .matricule-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.85rem;
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
                <a class="nav-link " href="dashboard.php">
                    <i class="fas fa-home me-2"></i>
                    Tableau de bord
                </a>
                <a class="nav-link" href="classes.php">
                    <i class="fas fa-chalkboard me-2"></i>
                    Classes
                </a>

                <a class="nav-link active" href="etudiants.php">
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
                <h4 class="mb-1">Gestion des Étudiants</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item active">Étudiants</li>
                    </ol>
                </nav>
            </div>
            <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#addEtudiantModal">
                <i class="fas fa-plus me-2"></i>Nouvel Étudiant
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

        <!-- Liste des étudiants -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Liste des Étudiants</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="etudiantsTable">
                        <thead>
                            <tr>
                                <th>Matricule</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Email</th>
                                <th>Classe</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($etudiants as $etudiant): ?>
                            <tr>
                                <td>
                                    <span class="matricule-badge">
                                        <?php echo htmlspecialchars($etudiant['matricule']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($etudiant['nom']); ?></td>
                                <td><?php echo htmlspecialchars($etudiant['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($etudiant['email']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($etudiant['classe_niveau'] . ' - ' . $etudiant['classe_nom']); ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" 
                                            onclick="editEtudiant(<?php echo htmlspecialchars(json_encode($etudiant)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteEtudiant(<?php echo $etudiant['id']; ?>, '<?php echo htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']); ?>')">
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

    <!-- Modal Ajout Étudiant -->
    <div class="modal fade" id="addEtudiantModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nouvel Étudiant</h5>
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
                            <label for="prenom" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="classe_id" class="form-label">Classe</label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">Sélectionner une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>">
                                    <?php echo htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
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

    <!-- Modal Modification Étudiant -->
    <div class="modal fade" id="editEtudiantModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier l'Étudiant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="etudiant_id" id="edit_etudiant_id">
                        <div class="mb-3">
                            <label for="edit_nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="edit_nom" name="nom" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_prenom" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="edit_prenom" name="prenom" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_classe_id" class="form-label">Classe</label>
                            <select class="form-select" id="edit_classe_id" name="classe_id" required>
                                <option value="">Sélectionner une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>">
                                    <?php echo htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
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
    <div class="modal fade" id="deleteEtudiantModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer l'étudiant <strong id="delete_etudiant_nom"></strong> ?</p>
                    <p class="text-danger">Cette action est irréversible.</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="etudiant_id" id="delete_etudiant_id">
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
            $('#etudiantsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json'
                }
            });
        });

        function editEtudiant(etudiant) {
            document.getElementById('edit_etudiant_id').value = etudiant.id;
            document.getElementById('edit_nom').value = etudiant.nom;
            document.getElementById('edit_prenom').value = etudiant.prenom;
            document.getElementById('edit_email').value = etudiant.email;
            document.getElementById('edit_classe_id').value = etudiant.classe_id;
            new bootstrap.Modal(document.getElementById('editEtudiantModal')).show();
        }

        function deleteEtudiant(id, nom) {
            document.getElementById('delete_etudiant_id').value = id;
            document.getElementById('delete_etudiant_nom').textContent = nom;
            new bootstrap.Modal(document.getElementById('deleteEtudiantModal')).show();
        }
    </script>
</body>
</html>
