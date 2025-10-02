<?php
// No GET parameter login bypass - all authentication goes through the API
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Hospital HR System Login</title>
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
  :root{
  --hvh-navy:#0A1D4D;
  --hvh-navy-900:#07143A;
  --logo-url: url("https://hebbkx1anhila5yf.public.blob.vercel-storage.com/hvh-logo-Qb4GMWRmiAugUk366zbp9Rmm3TLZww.png");
}


.hvh-bg{
  min-height:100vh;
  background: linear-gradient(180deg, var(--hvh-navy) 0%, var(--hvh-navy-900) 100%);
  position:relative;
  overflow:hidden;
}


.hvh-bg::before{
  content:"";
  position:fixed; inset:0;
  pointer-events:none; z-index:0;
  background-image: var(--logo-url);
  background-repeat: repeat;
  background-size: 200px 200px;
  opacity:.18;
  filter: blur(1px) brightness(1.1);
  animation: logosShift 40s linear infinite alternate;
}


.hvh-bg::after{
  content:"";
  position:fixed; inset:-20%;
  pointer-events:none; z-index:1;
  background:
    radial-gradient(40vmax 30vmax at 20% 25%, rgba(255,255,255,.14), transparent 60%),
    radial-gradient(35vmax 28vmax at 80% 70%, rgba(212,175,55,.18), transparent 65%),
    radial-gradient(28vmax 24vmax at 50% 85%, rgba(255,255,255,.10), transparent 70%);
  filter: blur(35px);
  animation: hvhGlow 25s ease-in-out infinite alternate;
}


@keyframes logosShift {
  0%   { background-position: 0 0; }
  100% { background-position: -200px -150px; }
}
@keyframes hvhGlow {
  0%   { transform: scale(1) translate(0,0); }
  100% { transform: scale(1.05) translate(-2%, -1%); }
}

@media (max-width:640px){
  .hvh-bg::before{
    background-size: 150px 150px;
    opacity:.14;
  }
}

  </style>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.querySelector('form');
      const emailInput = document.getElementById('email');
      const passwordInput = document.getElementById('password');

      form.addEventListener('submit', function(e) {
        e.preventDefault();

        const email = emailInput.value;
        const password = passwordInput.value;

        if (!email || !password) {
          Swal.fire('Error', 'Please fill in all fields', 'error');
          return;
        }

        // Submit to login API
        fetch('api/index.php?route=auth/login', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ username: email, password: password })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success === false) {
            Swal.fire('Error', data.message || data.error || 'Login failed', 'error');
          } else if (data.two_factor_required) {
            Swal.fire('2FA Required', data.message, 'info');
          } else if (data.success === true || data.message === 'Login successful' || data.message === 'Login successful.') {
            Swal.fire('Success', 'Login successful!', 'success').then(() => {
              // Determine redirect based on user role
              const user = data.data?.user || data.user;
              let redirect_url = 'index.php';
              
              if (user && user.role) {
                if (user.role === 'System Admin') {
                  redirect_url = 'admin_landing.php';
                } else if (user.role === 'Employee') {
                  redirect_url = 'employee_landing.php';
                }
              }
              
              // Use provided redirect_url if available, otherwise use role-based redirect
              window.location.href = data.redirect_url || redirect_url;
            });
          } else {
            Swal.fire('Error', data.message || data.error || 'Unexpected response format', 'error');
          }
        })
        .catch(error => {
          console.error('Login Error:', error);
          Swal.fire({
            icon: 'error',
            title: 'Login Error', 
            text: 'An error occurred during login. Please check your connection and try again.',
            footer: window.location.hostname !== 'localhost' ? 
              '<small>If this persists, contact system administrator</small>' : ''
          });
        });
      });
    });
  </script>
</head>
<body class="hvh-bg flex flex-col items-center justify-start md:justify-center pt-4 md:pt-0 px-4 font-sans">
  <!-- Multi-logo layer (masked by glow) -->
  <div class="hvh-logos" aria-hidden="true"></div>

  <!-- Your original container -->
  <div class="relative z-[3] w-full max-w-md md:max-w-3xl rounded-2xl overflow-hidden shadow-lg flex flex-col md:grid md:grid-cols-2 bg-transparent">
    <div class="flex items-center justify-center pt-2 pb-3 md:hidden">
      <div class="bg-white rounded-full flex items-center justify-center w-40 h-40 shadow-md ring-2 ring-gray-300">
        <img
          src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/hvh-logo-Qb4GMWRmiAugUk366zbp9Rmm3TLZww.png"
          alt="H Vill Hospital Logo"
          class="max-h-36 w-auto object-contain"
        />
      </div>
    </div>

    <div class="bg-slate-100 p-6 md:p-10 md:rounded-l-2xl rounded-t-2xl md:rounded-t-none">
      <div class="text-center mb-6 md:mb-8">
        <h1 class="text-2xl font-bold">Welcome back</h1>
        <p class="text-gray-600">Login to your Hospital account</p>
      </div>
      <form class="space-y-5 md:space-y-6">
        <div>
          <label for="email" class="block text-sm font-medium">Email</label>
          <input
            id="email"
            type="email"
            placeholder="m@example.com"
            required
            class="mt-2 w-full rounded-md border-2 border-gray-400 px-3 py-2 focus:outline-none focus:border-gray-600"
          />
        </div>
        <div>
          <label for="password" class="block text-sm font-medium">Password</label>
          <input
            id="password"
            type="password"
            required
            class="mt-2 w-full rounded-md border-2 border-gray-400 px-3 py-2 focus:outline-none focus:border-gray-600"
          />
        </div>

        <div class="text-right -mt-2">
          <a href="#" class="text-sm font-semibold hover:underline">Forgot your password?</a>
        </div>
        <button type="submit" class="w-full rounded-md bg-black py-2 text-white hover:bg-gray-800 transition">
          Login
        </button>

        <p class="text-center text-sm">
          Don’t have an account?
          <a href="#" class="font-semibold hover:underline">Sign up</a>
        </p>
      </form>
    </div>
    <div class="hidden md:flex items-center justify-center bg-slate-300 md:rounded-r-2xl p-8">
      <img
        src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/hvh-logo-Qb4GMWRmiAugUk366zbp9Rmm3TLZww.png"
        alt="H Vill Hospital Logo"
        class="max-h-72 w-auto object-contain"
      />
    </div>
  </div>

  <p class="relative z-[3] mt-4 md:mt-6 text-center text-xs text-gray-300 max-w-md md:max-w-none">
    By clicking continue, you agree to our
    <a href="#" class="underline hover:text-white">Terms of Service</a> and
    <a href="#" class="underline hover:text-white">Privacy Policy</a>.
  </p>
</body>
</html>
