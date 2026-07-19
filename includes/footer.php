
               <br>

          </section>
          <footer class="footer">
               <p>&copy; <?php echo date("Y"); ?> Scholarship Management System (SMS).<br>Version 1.0 | College of Maasin</p>
          </footer>

          <?php if (isset($useChart) && $useChart === true): ?>
               <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
               <script src="assets/js/chart.js"></script>
          <?php endif; ?>

          <?php if (isset($page_js)): ?>
               <script src="<?= SITE_URL ?>/assets/js/<?= $page_js ?>"></script>
          <?php endif; ?>
    </body>


</html>