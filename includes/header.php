<?php
require_once __DIR__ . '/../config/config.php';
checkAuth();
?>

<!DOCTYPE html>
<html>
    <head>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@100..900&display=swap" rel="stylesheet">
        <link href="<?= SITE_BASE ?>/assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
        <?php if (isset($page_css)): ?>
            <link rel="stylesheet" href="<?= SITE_BASE ?>/assets/css/<?= $page_css ?>?v=<?= time() ?>">
        <?php endif; ?>
        <link rel="icon" href="<?= SITE_BASE ?>/assets/img/cmlogoremove.png">
        <script>
            window.SITE_BASE = <?= json_encode(SITE_BASE) ?>;
            window.SITE_URL = <?= json_encode(SITE_URL) ?>;
            window.API_BASE = window.SITE_BASE + '/api';

            // If the sign-in session has ended, any API call answers 401. Instead of every
            // screen showing a bare "Unauthorized.", explain it once and go to Sign In.
            (function () {
                const nativeFetch = window.fetch.bind(window);
                let redirecting = false;
                window.fetch = async function (input, init) {
                    const res = await nativeFetch(input, init);
                    const url = typeof input === "string" ? input : (input && input.url) || "";
                    if (res.status === 401 && (url.indexOf("/api/") !== -1 || url.indexOf("api/") === 0)) {
                        if (!redirecting) {
                            redirecting = true;
                            alert("Your session has ended. Please sign in again — nothing on this page was saved.");
                            window.location.href = window.SITE_BASE + "/login";
                        }
                        return new Promise(() => {}); // stop the page from also showing its own error
                    }
                    return res;
                };
            })();
        </script>
        <script src="<?= SITE_BASE ?>/assets/lib/lucide.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="<?= SITE_BASE ?>/assets/js/api.js?v=<?= time() ?>"></script>
        <title><?= htmlspecialchars(($page_title ?? 'Scholarship Portal') . ' - Scholarship Portal') ?></title>
    </head>


<body>
    <div id="pageProgressBar" class="page-progress-bar"></div>
    <section class="home">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="top-nav-sticky">
            <div class="top-nav">
                <h2><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h2>
                <?php include __DIR__ . '/../includes/navbar.php'; ?>
            </div>
        </div>
