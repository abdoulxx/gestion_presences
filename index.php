<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Présences - Accueil</title>
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
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            color: white;
            padding: 100px 0;
            margin-bottom: 50px;
        }
        
        .feature-card {
            border: none;
            border-radius: 15px;
            transition: transform 0.3s ease;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .btn-custom {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .btn-custom:hover {
            background-color: var(--secondary-color);
            color: white;
            transform: translateY(-2px);
        }
        
        .navbar-nav .nav-link {
            color: white !important;
            margin: 0 10px;
            position: relative;
        }
        
        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: white;
            transition: width 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover::after {
            width: 100%;
        }
    </style>
</head>
<body>
    <!-- Barre de navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-user-graduate me-2"></i>
                Gestion des Présences
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#fonctionnalites">Fonctionnalités</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="auth/parent_login.php">Espace Parents/Étudiants</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin/">Administration</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="enseignant/">Enseignants</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Section Hero -->
    <section class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Système de Gestion des Présences</h1>
            <p class="lead mb-5">Une solution moderne pour le suivi en temps réel de l'assiduité des élèves</p>
            <a href="auth/parent_login.php" class="btn btn-custom btn-lg me-3">
                <i class="fas fa-user-graduate me-2"></i>Espace Parents/Étudiants
            </a>
            <a href="enseignant/" class="btn btn-outline-light btn-lg me-3">
                <i class="fas fa-chalkboard-teacher me-2"></i>Espace Enseignant
            </a>
            <a href="admin/" class="btn btn-outline-light btn-lg">
                <i class="fas fa-user-shield me-2"></i>Administration
            </a>
        </div>
    </section>

    <!-- Section Fonctionnalités -->
    <section id="fonctionnalites" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Nos Fonctionnalités</h2>
            <div class="row">
                <!-- Carte Suivi en Temps Réel -->
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-clock feature-icon"></i>
                            <h5 class="card-title">Suivi en Temps Réel</h5>
                            <p class="card-text">Suivez l'assiduité des élèves en temps réel avec des notifications instantanées.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Carte Gestion des Justificatifs -->
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-file-alt feature-icon"></i>
                            <h5 class="card-title">Gestion des Justificatifs</h5>
                            <p class="card-text">Soumission et validation en ligne des justificatifs d'absence.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Carte Statistiques -->
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-bar feature-icon"></i>
                            <h5 class="card-title">Statistiques Détaillées</h5>
                            <p class="card-text">Analyses et rapports détaillés sur l'assiduité des élèves.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <!-- Carte Communication -->
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-comments feature-icon"></i>
                            <h5 class="card-title">Communication Directe</h5>
                            <p class="card-text">Système de notification pour une communication efficace entre tous les acteurs.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Carte Interface Intuitive -->
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-laptop feature-icon"></i>
                            <h5 class="card-title">Interface Intuitive</h5>
                            <p class="card-text">Une interface moderne et facile à utiliser pour tous les utilisateurs.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Carte Accès Mobile -->
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-mobile-alt feature-icon"></i>
                            <h5 class="card-title">Accès Mobile</h5>
                            <p class="card-text">Accessible sur tous les appareils pour une gestion en mobilité.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>