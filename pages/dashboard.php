<?php
$page_css = "dashboard.css";
$page_js = "chart.js";
include __DIR__ . '/../includes/header.php';
?>

    <div class="top-nav">
        <h2>Dashboard</h2>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
</div>

<div class="main-content">
    <!-- Cards -->
    <div class="cards">
        <div class="card blue">
            <i class="bx bx-user"></i>
            <span class="text card-text">Total Applicants</span>

        </div>

        <div class="card orange">
            <i class="bx bx-task"></i>
            <span class="text card-text">Under Evaluation</span>

        </div>

        <div class="card green">
            <i class="bx bxs-graduation"></i>
            <span class="text card-text">Active Scholars</span>

        </div>

        <div class="card red">
            <i class="bx bxs-calendar"></i>
            <span class="text card-text">Renewal Due</span>

        </div>
    </div>


    <!-- Charts -->
    <div class="charts-wrapper">
        <div class="chart-block">
            <div class="text chart-title">Scholarship Distribution</div>
                <canvas id="scholarshipChart" role="img"
                aria-label="Bar chart of scholarship distribution by type.">
                </canvas>
            </div>

        <div class="chart-block">
            <div class="text chart-title">Monthly Applications</div>
                <canvas id="monthlyChart" role="img"
                aria-label="Bar chart of monthly applications by scholarship .">
                </canvas>
            </div>
        </div>
    </div>
    <!-- Notifications -->
    <div class="notification-container">
        <h3>Notifications</h3>
        <div class="notification-item">
            <i class="bx bx-bell"></i>
            <div>
                <h4>Renewal Deadline</h4>

            </div>
        </div>
        <div class="notification-item">
            <i class="bx bx-user-plus"></i>
            <div>
                <h4>New Applicant</h4>

            </div>
        </div>

        <div class="notification-item">
            <i class="bx bx-calendar"></i>
            <div>
                <h4>Evaluation Meeting</h4>

        </div>
        </div>

    </div>

</div>
<?php if (isset($page_css)): ?>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/<?= $page_css ?>">
<?php endif; ?>

 <?php include __DIR__ . '/../includes/footer.php'; ?>