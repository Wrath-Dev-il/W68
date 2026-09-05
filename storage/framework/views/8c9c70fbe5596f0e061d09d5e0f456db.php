<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login — w68 Autoparts & Service Center</title>
  <meta name="description" content="Sign in to w68 Autoparts & Service Center — secure access to your account." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="<?php echo e(asset('css/login.css')); ?>">
  <style>
    .bg-slide {
      background-size: 65% !important;
      background-position: 15% center !important;
      background-repeat: no-repeat !important;
      background-color: #000 !important;
    }
    .bg-slide:nth-child(1) { background-image: url('<?php echo e(asset("build/assets/images/login_background.gif")); ?>'); }
    .bg-slide:nth-child(2) { 
      background-image: url('<?php echo e(asset("build/assets/images/login_background1.gif")); ?>'); 
      background-size: 90% !important;
    }
    .bg-slide:nth-child(3) { 
      background-image: url('<?php echo e(asset("build/assets/images/login_background3.gif")); ?>'); 
      background-size: 75% !important;
    }
  </style>
</head>
<body>
    <?php echo $__env->make('partials.global.w68-loader', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


  <!-- ── Background Slideshow ────────────────────────────── -->
  <div class="bg-slideshow" aria-hidden="true">
    <div class="bg-slide active"></div>
    <div class="bg-slide"></div>
    <div class="bg-slide"></div>
  </div>
  <div class="bg-overlay" aria-hidden="true"></div>

  <!-- ── Page Layout ─────────────────────────────────────── -->
  <div class="page-wrapper">

    <!-- Left — Background visible -->
    <div class="left-side"></div>

    <!-- Right — Login Form -->
    <div class="right-side">
      <div class="form-texture" aria-hidden="true"></div>

      <div class="form-content">

        <!-- Logo & Company Name -->
        <div class="logo-area">
          <img
            src="<?php echo e(asset('build/assets/images/login_logo.png.png')); ?>"
            alt="w68 Autoparts Logo"
            class="logo-img"
            id="logoImg"
          />
          <div class="company-name">w68 Autoparts &amp; Service Center</div>
        </div>

        <div class="gold-divider"></div>

        <!-- Error Alert -->
        <div class="alert <?php if(session('error')): ?> show <?php endif; ?>" id="alertBox" role="alert" aria-live="polite">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <span id="alertMsg"><?php if(session('error')): ?> <?php echo e(session('error')); ?> <?php else: ?> Please fill in all fields. <?php endif; ?></span>
        </div>

        <!-- Login Form -->
        <form id="loginForm" method="POST" action="<?php echo e(route('login')); ?>" novalidate>
          <?php echo csrf_field(); ?>

          <!-- User-ID -->
          <div class="form-group">
            <label for="userId">User-ID</label>
            <div class="input-wrap">
              <input
                type="text"
                id="userId"
                name="email"
                placeholder="Enter your User-ID"
                autocomplete="username"
                required
              />
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
              </span>
            </div>
          </div>

          <!-- Password -->
          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
              <input
                type="password"
                id="password"
                name="password"
                placeholder="••••••••"
                autocomplete="current-password"
                required
              />
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
              </span>
              <button type="button" class="toggle-pass" id="togglePass" aria-label="Toggle password visibility">
                <svg id="eyeOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                </svg>
                <svg id="eyeClosed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                  <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                  <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Login Button -->
          <button type="submit" class="btn-login" id="submitBtn">
            <span class="btn-text">Login</span>
            <div class="spinner"></div>
          </button>
        </form>

        <p class="footer-note">
          &copy; 2025 w68 Autoparts &amp; Service Center
        </p>
      </div>
    </div>
  </div>

  <!-- ── Dot Indicators ──────────────────────────────────── -->
  <div class="slide-dots" aria-label="Background slide indicators">
    <span class="dot active" data-slide="0"></span>
    <span class="dot" data-slide="1"></span>
    <span class="dot" data-slide="2"></span>
  </div>

  <!-- Lockout Modal -->
  <?php if(session('lockout')): ?>
  <div id="lockoutModal" class="modal-backdrop show">
    <div class="modal-card">
      <div class="modal-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
      </div>
      <h3 class="modal-title">System Temporarily Locked</h3>
      <p class="modal-desc">You have exceeded the maximum login attempts. Please wait until the timer below expires to try again.</p>
      <div class="timer-display">
        <span id="countdownMin">03</span>:<span id="countdownSec">00</span>
      </div>
      <button type="button" class="btn-modal-close" onclick="closeLockoutModal()">Close</button>
    </div>
  </div>
  <?php endif; ?>

  <?php
    $developerOtpSeconds = (int) session('developer_otp_seconds', 0);
    if (!$developerOtpSeconds && session('developer_otp_login_id')) {
      $developerOtpSeconds = max(0, (int) session('developer_otp_expires_at', 0) - time());
    }
    $developerOtpEmail = session('developer_otp_email', session('developer_otp_email_mask'));
    $developerOtpLockoutSeconds = (int) session('developer_otp_lockout_seconds', 0);
    $showDeveloperOtp = session('developer_otp') || ($developerOtpSeconds > 0 && session('developer_otp_login_id'));
  ?>

  <?php if($showDeveloperOtp || session('developer_otp_lockout')): ?>
  <div id="developerOtpModal" class="modal-backdrop show">
    <form method="POST" action="<?php echo e(route('developer.otp.verify')); ?>" class="modal-card otp-modal-card" autocomplete="off">
      <?php echo csrf_field(); ?>
      <div class="modal-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
          <path d="M9 12l2 2 4-4"></path>
        </svg>
      </div>
      <h3 class="modal-title">Developer OTP Verification</h3>

      <?php if(session('developer_otp_lockout')): ?>
        <p class="modal-desc">Developer OTP access is locked after 5 failed attempts.</p>
        <div class="timer-display otp-lockout-timer" id="developerLockoutTimer">24:00:00</div>
        <button type="button" class="btn-modal-close" onclick="closeDeveloperOtpModal()">Close</button>
      <?php else: ?>
        <?php if($developerOtpEmail): ?>
          <p class="modal-desc">OTP email: <span class="otp-email"><?php echo e($developerOtpEmail); ?></span></p>
        <?php endif; ?>

        <?php if(session('developer_otp_notice') || session('developer_otp_mail_pending')): ?>
          <div class="otp-status otp-status-success" id="developerOtpStatus">
            <?php echo e(session('developer_otp_notice', 'Sending developer OTP to your email...')); ?>

          </div>
        <?php endif; ?>

        <?php if(session('developer_otp_error')): ?>
          <div class="otp-status otp-status-error"><?php echo e(session('developer_otp_error')); ?></div>
        <?php endif; ?>

        <label for="otpCode" class="otp-label">6-digit OTP code</label>
        <input
          type="text"
          id="otpCode"
          name="otp_code"
          class="otp-input"
          inputmode="numeric"
          pattern="[0-9]{6}"
          maxlength="6"
          placeholder="000000"
          required
          autofocus
        />
        <div class="otp-meta">
          <span>Expires in</span>
          <strong><span id="otpCountdownMin">02</span>:<span id="otpCountdownSec">00</span></strong>
        </div>
        <div class="otp-actions">
          <button type="button" class="btn-modal-close" onclick="closeDeveloperOtpModal()">Cancel</button>
          <button type="submit" class="btn-modal-primary" id="otpSubmitBtn">Verify OTP</button>
        </div>
      <?php endif; ?>
    </form>
  </div>
  <?php endif; ?>

  <script>
    window.loginRoute = "<?php echo e(route('login')); ?>";
    window.csrfToken = "<?php echo e(csrf_token()); ?>";
    <?php if(session('lockout')): ?>
      window.lockoutSeconds = <?php echo e(session('seconds')); ?>;
    <?php else: ?>
      window.lockoutSeconds = 0;
    <?php endif; ?>
    window.developerOtpSeconds = <?php echo e($developerOtpSeconds ?? 0); ?>;
    window.developerOtpLockoutSeconds = <?php echo e($developerOtpLockoutSeconds ?? 0); ?>;
    window.developerOtpMailPending = <?php echo json_encode((bool) session('developer_otp_mail_pending'), 15, 512) ?>;
    window.developerOtpSendRoute = "<?php echo e(route('developer.otp.send')); ?>";

    function closeLockoutModal() {
      const modal = document.getElementById('lockoutModal');
      if (modal) {
        modal.classList.remove('show');
      }
    }

    function closeDeveloperOtpModal() {
      const modal = document.getElementById('developerOtpModal');
      if (modal) {
        modal.classList.remove('show');
      }
    }
  </script>
  <script src="<?php echo e(asset('js/login.js')); ?>"></script>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\hatdog\resources\views/login.blade.php ENDPATH**/ ?>