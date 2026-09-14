
          </section>
           <link href="<?= SITE_BASE ?>/assets/css/footer.css" rel="stylesheet">
          <footer class="footer">
               <p>&copy; <?php echo date("Y"); ?> Scholarship Management System (SMS).<br>Version 1.0 | College of Maasin</p>
          </footer>

          <?php if (isset($useChart) && $useChart === true): ?>
               <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
               <script src="<?= SITE_BASE ?>/assets/js/chart.js"></script>
          <?php endif; ?>

          <?php if (isset($extra_js) && is_array($extra_js)): foreach ($extra_js as $extraJsFile): ?>
               <script src="<?= SITE_BASE ?>/assets/js/<?= $extraJsFile ?>?v=<?= time() ?>"></script>
          <?php endforeach; endif; ?>

          <?php if (isset($page_js)): ?>
               <script src="<?= SITE_BASE ?>/assets/js/<?= $page_js ?>?v=<?= time() ?>"></script>
          <?php endif; ?>
          <script>
            document.addEventListener('DOMContentLoaded', () => {
              if (typeof lucide !== 'undefined') {
                lucide.createIcons();
              }

              const bar = document.getElementById('pageProgressBar');
              if (!bar) return;

              // Animate progress on initial load completion
              bar.style.transform = 'scaleX(1)';
              setTimeout(() => {
                bar.style.opacity = '0';
                setTimeout(() => {
                  bar.classList.add('no-transition');
                  bar.style.transform = 'scaleX(0)';
                  void bar.offsetHeight; // Force reflow
                  bar.classList.remove('no-transition');
                  bar.style.opacity = '1';
                }, 400);
              }, 300);

              // Trigger progress bar when clicking internal links
              document.addEventListener('click', (e) => {
                const link = e.target.closest('a');
                if (link && link.href && !link.target && !link.hasAttribute('download')) {
                  const url = new URL(link.href, window.location.origin);
                  if (url.origin === window.location.origin && url.pathname !== window.location.pathname) {
                    bar.style.opacity = '1';
                    bar.style.transform = 'scaleX(0.7)';
                  }
                }
              });

              window.addEventListener('beforeunload', () => {
                if (bar) {
                  bar.style.opacity = '1';
                  bar.style.transform = 'scaleX(0.9)';
                }
              });

              // Dynamic scroll shadow for seamless top header
              const stickyNav = document.querySelector('.top-nav-sticky');
              if (stickyNav) {
                const handleScroll = () => {
                  if (window.scrollY > 10) {
                    stickyNav.classList.add('is-scrolled');
                  } else {
                    stickyNav.classList.remove('is-scrolled');
                  }
                };
                window.addEventListener('scroll', handleScroll, { passive: true });
                handleScroll();
              }

              // Fetch actual notification count for header badge
              const badgeEl = document.getElementById('navNotifBadge');
              if (badgeEl && typeof apiListNotifications === 'function') {
                apiListNotifications().then(res => {
                  const count = (res.data || []).length;
                  badgeEl.textContent = count;
                  if (count === 0) {
                    badgeEl.style.display = 'none';
                  } else {
                    badgeEl.style.display = 'inline-flex';
                  }
                }).catch(() => {
                  badgeEl.style.display = 'none';
                });
              }

              // Profile dropdown toggle on click
              const profileWrapper = document.querySelector('.nav-profile-dropdown-wrapper');
              if (profileWrapper) {
                const profileBtn = profileWrapper.querySelector('.nav-profile-btn');
                if (profileBtn) {
                  profileBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    profileWrapper.classList.toggle('active');
                  });
                  document.addEventListener('click', (e) => {
                    if (!profileWrapper.contains(e.target)) {
                      profileWrapper.classList.remove('active');
                    }
                  });
                }
              }

              // Global ESC Key listener to close any active modal
              document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' || e.key === 'Esc') {
                  const openModals = document.querySelectorAll('.custom-modal-overlay.open, .applicant-overlay.open, .notification-overlay.open, .modal-overlay.open');
                  openModals.forEach((overlay) => {
                    const closeBtn = overlay.querySelector('.custom-modal-close, .modal-close, #closePanelBtn, #modalClose, #closeViewBtn, [id$="CloseBtn"], [id$="CancelBtn"]');
                    if (closeBtn) {
                      closeBtn.click();
                    } else {
                      overlay.classList.remove('open');
                    }
                  });
                }
              });

              // Automatically lock background body scroll when any modal opens
              let isLocking = false;
              const updateBodyScrollLock = () => {
                if (isLocking) return;
                const openModal = document.querySelector('.custom-modal-overlay.open, .applicant-overlay.open, .notification-overlay.open, .modal-overlay.open');
                const shouldLock = Boolean(openModal);
                if (document.body.classList.contains('modal-open') !== shouldLock) {
                  isLocking = true;
                  document.body.classList.toggle('modal-open', shouldLock);
                  isLocking = false;
                }
              };
              updateBodyScrollLock();
              const modalObserver = new MutationObserver(updateBodyScrollLock);
              modalObserver.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
            });
          </script>
          <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    </body>


</html>