<?php
if (!isset($_SESSION)) {
    session_start();
}
?>
<div class="sidebar">
    <div class="p-3">
        <div class="d-flex align-items-center mb-4 mt-2">
            <i class="fas fa-chalkboard-teacher fs-4 me-2"></i>
            <h5 class="mb-0">Espace Enseignant</h5>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
               href="dashboard.php">
                <i class="fas fa-home me-2"></i>
                Tableau de bord
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'presences.php' ? 'active' : ''; ?>" 
               href="presences.php">
                <i class="fas fa-user-check me-2"></i>
                Gestion des présences
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'emploi_temps.php' ? 'active' : ''; ?>" 
               href="emploi_temps.php">
                <i class="fas fa-calendar-alt me-2"></i>
                Emploi du temps
            </a>
            <a class="nav-link text-danger mt-auto" href="../auth/logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>
                Déconnexion
            </a>
        </nav>
    </div>
</div>
