<?php require_once __DIR__ . '/../config.php'; ?>


<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
        <link href="<?= SITE_URL ?>/assets/lib/bulma.min.css" rel="stylesheet">
        <?php if (isset($page_css)): ?>
            <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/<?= $page_css ?>">
        <?php endif; ?>
        <link rel="icon" href="<?= SITE_URL ?>/assets/img/cmlogoremove.png">
        <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <title>Dashboard Menu</title>
            <link rel="icon" href="assets/img/cmlogoremove.png">
    </head>


<body>
    <section class="home">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
