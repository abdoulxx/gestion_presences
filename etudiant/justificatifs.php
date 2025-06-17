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

// Récupérer les absences non justifiées
$stmt = $conn->prepare("
    SELECT 
        p.id as presence_id,
        p.date,
        p.statut,
        m.nom as matiere_nom,
        CONCAT(u.prenom, ' ', u.nom) as enseignant_nom,
        e.heure_debut,
        e.heure_fin,
        (SELECT j.id 
         FROM justificatifs j 
         WHERE j.etudiant_id = p.etudiant_id 
         AND p.date BETWEEN j.date_debut AND j.date_fin
         LIMIT 1) as justificatif_id,
        (SELECT j.statut 
         FROM justificatifs j 
         WHERE j.etudiant_id = p.etudiant_id 
         AND p.date BETWEEN j.date_debut AND j.date_fin
         LIMIT 1) as justificatif_statut
    FROM presences p
    JOIN emploi_temps e ON p.cours_id = e.id
    JOIN matieres m ON e.matiere_id = m.id
    JOIN users u ON e.enseignant_id = u.id
    WHERE p.etudiant_id = ? 
    AND p.statut = 'absent'
    ORDER BY p.date DESC");
$stmt->execute([$user_id]);
$absences = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire de justificatif
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Vérifier les données
        if (!isset($_POST['presence_id']) || !isset($_POST['motif']) || empty($_FILES['document']['name'])) {
            throw new Exception("Veuillez remplir tous les champs obligatoires.");
        }

        // Vérifier le type de fichier
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($_FILES['document']['type'], $allowed_types)) {
            throw new Exception("Type de fichier non autorisé. Seuls les PDF, JPEG et PNG sont acceptés.");
        }

        // Créer le dossier de stockage s'il n'existe pas
        $upload_dir = '../uploads/justificatifs/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Générer un nom unique pour le fichier
        $file_extension = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('justificatif_') . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;

        // Déplacer le fichier
        if (!move_uploaded_file($_FILES['document']['tmp_name'], $file_path)) {
            throw new Exception("Erreur lors du téléchargement du fichier.");
        }

        // Récupérer la date de l'absence
        $stmt = $conn->prepare("SELECT date FROM presences WHERE id = ?");
        $stmt->execute([$_POST['presence_id']]);
        $date_absence = $stmt->fetchColumn();

        // Insérer le justificatif dans la base de données
        $stmt = $conn->prepare("
            INSERT INTO justificatifs 
            (etudiant_id, date_debut, date_fin, motif, document_path, statut) 
            VALUES (?, ?, ?, ?, ?, 'en_attente')");
        
        if (!$stmt->execute([$user_id, $date_absence, $date_absence, $_POST['motif'], $file_name])) {
            // Supprimer le fichier en cas d'erreur
            unlink($file_path);
            throw new Exception("Erreur lors de l'enregistrement du justificatif.");
        }

        $_SESSION['success_message'] = "Votre justificatif a été soumis avec succès.";
        header('Location: justificatifs.php');
        exit();

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Justificatifs - Espace Étudiant</title>
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

        .custom-file-label::after {
            content: "Parcourir";
        }

        .absence-card {
            transition: all 0.3s ease;
        }

        .absence-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }

        /* Styles pour la modale */
        .modal {
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-backdrop {
            display: none;
        }

        .modal.show {
            display: block;
            padding-right: 17px;
        }

        .modal-dialog {
            transition: transform 0.3s ease-out;
        }

        .modal.show .modal-dialog {
            transform: none;
        }

        .modal-content {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
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
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-home me-2"></i>
                    Tableau de bord
                </a>
                <a class="nav-link" href="cours.php">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Emploi du temps
                </a>
                <a class="nav-link" href="presences.php">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Mes présences
                </a>
                <a class="nav-link active" href="justificatifs.php">
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
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Justificatifs d'absence</h4>
                <p class="text-muted mb-0">
                    <?php echo htmlspecialchars($etudiant['classe_nom']); ?> - 
                    Niveau <?php echo htmlspecialchars($etudiant['niveau']); ?>
                </p>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <!-- Liste des absences -->
        <div class="row g-4">
            <?php foreach ($absences as $absence): ?>
            <div class="col-md-6">
                <div class="card absence-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title mb-1">
                                    <?php echo htmlspecialchars($absence['matiere_nom']); ?>
                                </h5>
                                <p class="text-muted mb-0">
                                    <?php echo htmlspecialchars($absence['enseignant_nom']); ?>
                                </p>
                            </div>
                            <?php if ($absence['justificatif_id']): ?>
                                <span class="badge bg-<?php 
                                    echo $absence['justificatif_statut'] === 'accepte' ? 'success' : 
                                        ($absence['justificatif_statut'] === 'refuse' ? 'danger' : 'warning'); 
                                    ?> status-badge">
                                    <?php echo ucfirst($absence['justificatif_statut']); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <i class="fas fa-calendar me-2 text-primary"></i>
                            <?php echo date('d/m/Y', strtotime($absence['date'])); ?>
                            <br>
                            <i class="fas fa-clock me-2 text-primary"></i>
                            <?php 
                            echo substr($absence['heure_debut'], 0, 5) . ' - ' . 
                                 substr($absence['heure_fin'], 0, 5); 
                            ?>
                        </div>

                        <?php if (!$absence['justificatif_id']): ?>
                        <button type="button" 
                                class="btn btn-primary btn-sm" 
                                data-bs-toggle="modal" 
                                data-bs-target="#justificatifModal<?php echo $absence['presence_id']; ?>">
                            <i class="fas fa-file-upload me-2"></i>
                            Soumettre un justificatif
                        </button>

                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($absences)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Aucune absence à justifier pour le moment.
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Modales des justificatifs -->
        <?php foreach ($absences as $absence): ?>
        <?php if (!$absence['justificatif_id']): ?>
        <div class="modal fade" id="justificatifModal<?php echo $absence['presence_id']; ?>" tabindex="-1" aria-labelledby="justificatifModalLabel<?php echo $absence['presence_id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="justificatifModalLabel<?php echo $absence['presence_id']; ?>">
                            Justifier l'absence du <?php echo date('d/m/Y', strtotime($absence['date'])); ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="modal-body">
                            <div class="mb-3">
                                <p class="text-muted">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Cours : <?php echo htmlspecialchars($absence['matiere_nom']); ?><br>
                                    Enseignant : <?php echo htmlspecialchars($absence['enseignant_nom']); ?><br>
                                    Horaire : <?php echo substr($absence['heure_debut'], 0, 5) . ' - ' . substr($absence['heure_fin'], 0, 5); ?>
                                </p>
                            </div>

                            <input type="hidden" name="presence_id" value="<?php echo $absence['presence_id']; ?>">
                            
                            <div class="mb-3">
                                <label for="motif<?php echo $absence['presence_id']; ?>" class="form-label">
                                    Motif de l'absence
                                </label>
                                <textarea class="form-control" 
                                          id="motif<?php echo $absence['presence_id']; ?>"
                                          name="motif" 
                                          rows="3" 
                                          required
                                          placeholder="Expliquez la raison de votre absence..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="document<?php echo $absence['presence_id']; ?>" class="form-label">
                                    Document justificatif
                                </label>
                                <input type="file" 
                                       class="form-control" 
                                       id="document<?php echo $absence['presence_id']; ?>"
                                       name="document"
                                       accept=".pdf,.jpg,.jpeg,.png"
                                       required>
                                <small class="text-muted">
                                    Formats acceptés : PDF, JPEG, PNG
                                </small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>
                                Envoyer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gestion des modales
        document.addEventListener('DOMContentLoaded', function() {
            // Nettoyer les modales précédentes
            var modals = document.querySelectorAll('.modal');
            modals.forEach(function(modal) {
                modal.addEventListener('hidden.bs.modal', function() {
                    // Réinitialiser le formulaire
                    this.querySelector('form').reset();
                });
            });

            // Initialiser les modales Bootstrap
            var modalElements = document.querySelectorAll('.modal');
            modalElements.forEach(function(modalElement) {
                new bootstrap.Modal(modalElement, {
                    backdrop: true,
                    keyboard: true,
                    focus: true
                });
            });
        });
    </script>
</body>
</html>
