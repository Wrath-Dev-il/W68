/* ── Background Slideshow ────────────────────────────── */
(function () {
  const slides = document.querySelectorAll('.bg-slide');
  const dots   = document.querySelectorAll('.dot');
  let current  = 0;
  let interval;
  const DURATION = 5000; // 5 seconds per slide

  function goTo(index) {
    if (!slides[current] || !dots[current]) return;
    slides[current].classList.remove('active');
    dots[current].classList.remove('active');
    current = index;
    if (!slides[current] || !dots[current]) return;
    slides[current].classList.add('active');
    dots[current].classList.add('active');
  }

  function next() {
    goTo((current + 1) % slides.length);
  }

  function startAuto() {
    clearInterval(interval);
    interval = setInterval(next, DURATION);
  }

  // Dot click
  dots.forEach(function (dot) {
    dot.addEventListener('click', function () {
      goTo(parseInt(this.dataset.slide, 10));
      startAuto();
    });
  });

  startAuto();
})();

/* ── Entrance animation ──────────────────────────────── */
function markLoginLoaded() {
  document.body.classList.add('loaded');
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', markLoginLoaded, { once: true });
} else {
  markLoginLoaded();
}

/* ── Toggle password visibility ──────────────────────── */
(function () {
  var btn    = document.getElementById('togglePass');
  var input  = document.getElementById('password');
  var open   = document.getElementById('eyeOpen');
  var closed = document.getElementById('eyeClosed');
  if (btn && input) {
    btn.addEventListener('click', function () {
      var isPass = input.type === 'password';
      input.type = isPass ? 'text' : 'password';
      open.style.display   = isPass ? 'none'  : 'block';
      closed.style.display = isPass ? 'block' : 'none';
    });
  }
})();

/* ── Ripple effect on button ─────────────────────────── */
const submitBtn = document.getElementById('submitBtn');
if (submitBtn) {
  submitBtn.addEventListener('click', function (e) {
    var btn  = this;
    var rect = btn.getBoundingClientRect();
    var rip  = document.createElement('span');
    var size = Math.max(rect.width, rect.height);
    rip.className = 'ripple';
    rip.style.cssText = 'width:' + size + 'px;height:' + size + 'px;left:' + (e.clientX - rect.left - size/2) + 'px;top:' + (e.clientY - rect.top - size/2) + 'px';
    btn.appendChild(rip);
    setTimeout(function () { rip.remove(); }, 700);
  });
}

/* ── Form submit & redirect ──────────────────────────── */
const loginForm = document.getElementById('loginForm');
if (loginForm) {
  loginForm.addEventListener('submit', function (e) {
    var userId   = document.getElementById('userId').value.trim();
    var password = document.getElementById('password').value;
    var alertEl  = document.getElementById('alertBox');
    var alertMsg = document.getElementById('alertMsg');
    var btn      = document.getElementById('submitBtn');

    if (loginForm.dataset.submitting === 'true') {
      e.preventDefault();
      return;
    }

    // Validation
    if (!userId || !password) {
      e.preventDefault();
      if (alertMsg && alertEl) {
        alertMsg.textContent = 'Please fill in your User-ID and password.';
        alertEl.classList.add('show');
      }
      return;
    }

    if (alertEl) alertEl.classList.remove('show');
    loginForm.dataset.submitting = 'true';

    if (btn) {
      btn.classList.add('loading');
      btn.disabled = true;
    }
  });
}

/* ── Lockout Countdown Timer ─────────────────────────── */
(function () {
  if (typeof window.lockoutSeconds !== 'undefined' && window.lockoutSeconds > 0) {
    let secondsLeft = Math.ceil(window.lockoutSeconds);
    const minEl = document.getElementById('countdownMin');
    const secEl = document.getElementById('countdownSec');
    const submitBtn = document.getElementById('submitBtn');

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.style.opacity = '0.6';
      submitBtn.style.cursor = 'not-allowed';
    }

    function updateDisplay() {
      const minutes = Math.floor(secondsLeft / 60);
      const seconds = secondsLeft % 60;
      if (minEl) minEl.textContent = String(minutes).padStart(2, '0');
      if (secEl) secEl.textContent = String(seconds).padStart(2, '0');
    }

    updateDisplay();

    const interval = setInterval(function () {
      secondsLeft--;
      if (secondsLeft <= 0) {
        clearInterval(interval);
        if (minEl) minEl.textContent = '00';
        if (secEl) secEl.textContent = '00';
        
        const modal = document.getElementById('lockoutModal');
        if (modal) {
          modal.classList.remove('show');
        }
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.style.opacity = '';
          submitBtn.style.cursor = '';
        }
      } else {
        updateDisplay();
      }
    }, 1000);
  }
})();

/* Developer OTP countdown and input guard */
(function () {
  const otpInput = document.getElementById('otpCode');
  const otpSubmitBtn = document.getElementById('otpSubmitBtn');
  const otpStatus = document.getElementById('developerOtpStatus');

  function setOtpStatus(message, isError) {
    if (!otpStatus) return;

    otpStatus.textContent = message;
    otpStatus.classList.toggle('otp-status-success', !isError);
    otpStatus.classList.toggle('otp-status-error', Boolean(isError));
  }

  if (otpInput) {
    otpInput.addEventListener('input', function () {
      this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });
  }

  if (window.developerOtpMailPending && window.developerOtpSendRoute && otpInput) {
    fetch(window.developerOtpSendRoute, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': window.csrfToken || ''
      },
      body: '{}'
    })
      .then(function (response) {
        return response.json().catch(function () {
          return { success: false, message: 'Unable to send developer OTP right now.' };
        }).then(function (body) {
          return { ok: response.ok, body: body };
        });
      })
      .then(function (result) {
        setOtpStatus(result.body.message || 'Developer OTP sent successfully.', !result.ok || !result.body.success);
      })
      .catch(function () {
        setOtpStatus('Unable to send developer OTP right now. Please check the mail settings and try again.', true);
      });
  }

  function formatClock(totalSeconds) {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    if (hours > 0) {
      return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    }

    return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
  }

  if (typeof window.developerOtpSeconds !== 'undefined' && window.developerOtpSeconds > 0) {
    let secondsLeft = Math.ceil(window.developerOtpSeconds);
    const minEl = document.getElementById('otpCountdownMin');
    const secEl = document.getElementById('otpCountdownSec');

    function updateOtpDisplay() {
      const minutes = Math.floor(secondsLeft / 60);
      const seconds = secondsLeft % 60;
      if (minEl) minEl.textContent = String(minutes).padStart(2, '0');
      if (secEl) secEl.textContent = String(seconds).padStart(2, '0');
    }

    updateOtpDisplay();

    const interval = setInterval(function () {
      secondsLeft--;

      if (secondsLeft <= 0) {
        clearInterval(interval);
        if (minEl) minEl.textContent = '00';
        if (secEl) secEl.textContent = '00';
        if (otpInput) otpInput.disabled = true;
        if (otpSubmitBtn) {
          otpSubmitBtn.disabled = true;
          otpSubmitBtn.textContent = 'OTP Expired';
        }
      } else {
        updateOtpDisplay();
      }
    }, 1000);
  }

  if (typeof window.developerOtpLockoutSeconds !== 'undefined' && window.developerOtpLockoutSeconds > 0) {
    let lockoutSeconds = Math.ceil(window.developerOtpLockoutSeconds);
    const lockoutEl = document.getElementById('developerLockoutTimer');

    function updateLockoutDisplay() {
      if (lockoutEl) lockoutEl.textContent = formatClock(lockoutSeconds);
    }

    updateLockoutDisplay();

    const interval = setInterval(function () {
      lockoutSeconds--;

      if (lockoutSeconds <= 0) {
        clearInterval(interval);
        if (lockoutEl) lockoutEl.textContent = '00:00';
      } else {
        updateLockoutDisplay();
      }
    }, 1000);
  }
})();
