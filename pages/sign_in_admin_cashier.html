<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin / Cashier Sign In</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <style>
        :root {
            --primary: #4f46e5;
            --primary-glow: rgba(79, 70, 229, 0.4);
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --glass: rgba(255, 255, 255, 0.85);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(-45deg, #c9d6ff, #e2e2e2, #aec0ff, #f0f2f5);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            overflow: hidden;
            padding: 20px;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 50px 40px;
            background: var(--glass);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            animation: cardEntrance 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        @keyframes cardEntrance {
            from { opacity: 0; transform: scale(0.9) translateY(30px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        /* Icon Styling */
        .header-icon {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), #764ba2);
            border-radius: 24px;
            color: #ffffff;
            font-size: 42px;
            margin: 0 auto 24px;
            box-shadow: 0 15px 35px var(--primary-glow);
            animation: iconFloat 3s ease-in-out infinite;
        }

        @keyframes iconFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Staggered Content Animation */
        .animate-item {
            opacity: 0;
            animation: fadeInUp 0.5s ease forwards;
        }

        .login-card h1 { animation-delay: 0.2s; font-size: 1.8rem; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; letter-spacing: -1px; }
        .login-card p { animation-delay: 0.3s; color: var(--text-muted); font-size: 0.95rem; margin-bottom: 35px; }
        .input-box:nth-child(1) { animation-delay: 0.4s; }
        .input-box:nth-child(2) { animation-delay: 0.5s; }
        .input-box:nth-child(3) { animation-delay: 0.6s; }
        .submit-btn { animation-delay: 0.7s; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .input-box {
            position: relative;
            margin-bottom: 24px;
            text-align: left;
        }

        .input-box label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--primary);
        }

        .input-box input {
            width: 100%;
            padding: 15px 18px;
            padding-right: 48px;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(148, 163, 184, 0.35);
            border-radius: 16px;
            font-size: 0.96rem;
            color: var(--text-dark);
            outline: none;
            transition: all 0.25s ease;
        }

        .input-box input:focus {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 14px 24px -10px var(--primary-glow);
            transform: translateY(-1px);
        }

        .input-box i {
            position: absolute;
            right: 16px;
            top: 46px;
            font-size: 1.25rem;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.2s ease, transform 0.2s ease, opacity 0.2s ease;
        }

        .input-box i:hover {
            color: var(--primary);
            transform: scale(1.05);
        }

        .tooltip-icon::after {
            content: attr(data-tooltip);
            position: absolute;
            top: -34px;
            right: 0;
            white-space: nowrap;
            background: rgba(15, 23, 42, 0.95);
            color: #fff;
            font-size: 0.75rem;
            padding: 7px 11px;
            border-radius: 10px;
            box-shadow: 0 12px 22px rgba(0,0,0,0.14);
            opacity: 0;
            visibility: hidden;
            transform: translateY(8px);
            transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s ease;
            z-index: 2;
            pointer-events: none;
        }

        .tooltip-icon:hover::after {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .submit-btn {
            width: 100%;
            padding: 16px 18px;
            background: var(--text-dark);
            color: white;
            border: none;
            border-radius: 18px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.16);
        }

        .submit-btn:hover {
            background: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 16px 34px rgba(79, 70, 229, 0.2);
        }

        .status-card {
            width: 100%;
            margin-bottom: 22px;
            padding: 0 16px;
            border-radius: 18px;
            border: 1px solid transparent;
            font-size: 0.92rem;
            font-weight: 600;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.35s ease, opacity 0.35s ease, padding 0.35s ease;
            opacity: 0;
            display: block;
        }

        .status-card.visible {
            max-height: 120px;
            opacity: 1;
            padding: 14px 16px;
        }

        .msg-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .msg-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>

<div class="login-card">
    <!-- Shield/Admin Icon Added Here -->
    <div class="header-icon animate-item"><i class='bx bx-shield-quarter'></i></div>

    <h1 class="animate-item">Admin Login</h1>
    <p class="animate-item">Authorized personnel access only</p>

    <div id="status-area" class="status-card animate-item" role="status"></div>

    <form action="http://localhost/GCST_Track_System/actions/process_admin_cashier.php" method="POST" id="authForm">
        <div class="input-box animate-item">
            <label for="email">Username or Email</label>
            <input id="email" type="text" name="email" placeholder="Username or Email" autocomplete="username" required>
            <i class='bx bxs-envelope'></i>
        </div>

        <div class="input-box animate-item">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" placeholder="••••••••" required>
            <i class='bx bx-hide tooltip-icon' id="toggleBtn" data-tooltip="Show or hide password"></i>
        </div>

        <div class="input-box animate-item">
            <label for="pin">PIN</label>
            <input id="pin" type="password" name="pin" placeholder="••••" maxlength="4" pattern="\d{4}" inputmode="numeric" required>
            <i class='bx bx-hide tooltip-icon' id="togglePinBtn" data-tooltip="Show or hide PIN"></i>
        </div>

        <button type="submit" class="submit-btn animate-item" id="loginBtn">
            Sign In
        </button>
    </form>
</div>

<script>
    // Visibility toggle helper
    const setupVisibilityToggle = (buttonId, fieldId) => {
        const button = document.getElementById(buttonId);
        const field = document.getElementById(fieldId);
        if (!button || !field) return;

        button.addEventListener("click", () => {
            const isHidden = field.type === "password";
            field.type = isHidden ? "text" : "password";
            button.classList.toggle("bx-show", !isHidden);
            button.classList.toggle("bx-hide", isHidden);
        });
    };

    setupVisibilityToggle("toggleBtn", "password");
    setupVisibilityToggle("togglePinBtn", "pin");
    // Handle status messages from URL
    const urlParams = new URLSearchParams(window.location.search);
    const statusArea = document.getElementById("status-area");

    if (urlParams.has('error') || urlParams.has('success')) {
        statusArea.classList.add('visible');

        const errorCode = urlParams.get('error');
        const successCode = urlParams.get('success');

        if (errorCode === 'wrongpassword') {
            statusArea.classList.add('msg-error');
            statusArea.innerText = "Invalid Password.";
            statusArea.setAttribute('data-tooltip', 'Please verify your password and try again.');

        } else if (errorCode === 'nouser') {
            statusArea.classList.add('msg-error');
            statusArea.innerText = "Authorized account not found.";
            statusArea.setAttribute('data-tooltip', 'Check your username/email and ensure your account exists.');

        } else if (errorCode === 'invalid') {
            statusArea.classList.add('msg-error');
            statusArea.innerText = "Invalid credentials. Please check your inputs.";
            statusArea.setAttribute('data-tooltip', 'Make sure username/email, password, and PIN are correct.');

        } else if (errorCode === 'suspended') {
            statusArea.classList.add('msg-error');
            statusArea.innerText = "Account suspended.";
            statusArea.setAttribute('data-tooltip', 'Your account is currently suspended. Contact the administrator.');
        } else if (errorCode === 'locked') {
            statusArea.classList.add('msg-error');
            statusArea.innerText = "Account suspended after 4 failed login attempts. Contact the administrator.";
            statusArea.setAttribute('data-tooltip', 'Your account is temporarily locked after too many failed attempts.');

        } else if (successCode === '1') {
            statusArea.classList.add('msg-success');
            statusArea.innerText = "Profile verified. Please login.";
            statusArea.setAttribute('data-tooltip', 'Verification successful, you may now sign in.');
        }
    }
</script>
</body>
</html>