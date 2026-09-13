// ===== TOGGLE PASSWORD =====
document.getElementById('togglePw').addEventListener('click', function () {
  const input = document.getElementById('password');
  const icon = this.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
});

// ===== LOGIN FORM =====
document.getElementById('loginForm').addEventListener('submit', function (e) {
  e.preventDefault();
  const email = document.getElementById('email');
  const password = document.getElementById('password');
  const emailErr = document.getElementById('emailError');
  const passErr = document.getElementById('passwordError');
  let valid = true;

  emailErr.textContent = '';
  passErr.textContent = '';
  email.classList.remove('error');
  password.classList.remove('error');

  if (!email.value.trim()) {
    emailErr.textContent = 'Email address is required.';
    email.classList.add('error');
    valid = false;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    emailErr.textContent = 'Please enter a valid email address.';
    email.classList.add('error');
    valid = false;
  }

  if (!password.value) {
    passErr.textContent = 'Password is required.';
    password.classList.add('error');
    valid = false;
  }

  if (!valid) return;

  const btn = document.getElementById('loginBtn');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Logging in...</span>';
  btn.disabled = true;

  fetch('/LYDO/lydo-system/backend/api/login.php', {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: email.value.trim(), password: password.value })
  })
    .then(res => res.json().then(data => ({ ok: res.ok, data })))
    .then(({ ok, data }) => {
      btn.innerHTML = '<i class="fas fa-sign-in-alt"></i><span>Login Here</span>';
      btn.disabled = false;
      if (ok && data.success) {
        window.location.href = '/LYDO/lydo-system/shared/youth/dashboard.php';
      } else {
        const msg = data.message || 'Login failed. Please try again.';
        if (msg.toLowerCase().includes('not found') || msg.toLowerCase().includes('account')) {
          emailErr.textContent = msg;
          email.classList.add('error');
        } else {
          passErr.textContent = msg;
          password.classList.add('error');
        }
      }
    })
    .catch(() => {
      btn.innerHTML = '<i class="fas fa-sign-in-alt"></i><span>Login Here</span>';
      btn.disabled = false;
      passErr.textContent = 'Connection error. Make sure XAMPP is running.';
    });
});

// ===== FORGOT PASSWORD =====
const fpOverlay = document.getElementById('fpOverlay');

if (document.querySelector('.forgot-link')) {
  document.querySelector('.forgot-link').addEventListener('click', (e) => {
    e.preventDefault();
    window.location.href = '/LYDO/lydo-system/forgot_password.php';
  });
}
if (document.getElementById('fpClose')) {
  document.getElementById('fpClose').addEventListener('click', () => fpOverlay && fpOverlay.classList.remove('open'));
}
if (document.getElementById('fpBack')) {
  document.getElementById('fpBack').addEventListener('click', (e) => { e.preventDefault(); fpOverlay && fpOverlay.classList.remove('open'); });
}
if (fpOverlay) {
  fpOverlay.addEventListener('click', (e) => { if (e.target === fpOverlay) fpOverlay.classList.remove('open'); });
}
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && fpOverlay) fpOverlay.classList.remove('open'); });

if (document.getElementById('fpForm')) {
  document.getElementById('fpForm').addEventListener('submit', function (e) {
    e.preventDefault();
    window.location.href = '/LYDO/lydo-system/forgot_password.php';
  });
}

// ===== REGISTER LINK =====
document.getElementById('goRegister').addEventListener('click', (e) => {
  e.preventDefault();
  window.location.href = 'index.html#get-started';
});
