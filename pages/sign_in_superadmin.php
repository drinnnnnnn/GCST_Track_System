﻿<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Sign In</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <style>
        :root {
            --primary: #b4232d;
            --primary-hover: #941b23;
            --text-main: #172033;
            --text-muted: #667085;
            --white: #ffffff;
            --error: #dc2626;
            --error-bg: #fef2f2;
            --error-border: #fecaca;
            --success: #15803d;
            --success-bg: #f0fdf4;
            --success-border: #dcfce7;
            --field-border: #d7dce4;
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
            padding: 24px;
            overflow-y: auto;
            background:
                linear-gradient(rgba(242, 244, 247, 0.62), rgba(242, 244, 247, 0.62)),
                url('../assets/images/bg/Granby.jpg') center / cover no-repeat fixed;
        }

        .login-card {
            width: 100%;
            max-width: 390px;
            padding: 30px 32px 28px;
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid rgba(215, 220, 228, 0.8);
            border-top: 3px solid var(--primary);
            border-radius: 16px;
            box-shadow: 0 12px 30px rgba(23, 32, 51, 0.12);
        }

        .logo-container {
            text-align: center;
            margin-bottom: 14px;
        }

        .school-logo {
            display: block;
            width: 58px;
            height: 58px;
            object-fit: contain;
            margin: 0 auto;
        }

        .login-card h1 {
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 5px;
            text-align: center;
            letter-spacing: -0.035em;
        }

        .login-card p.subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 24px;
            text-align: center;
            line-height: 1.4;
        }
        
        .input-box {
            position: relative;
            margin-bottom: 16px;
            text-align: left;
        }

        .input-box label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
            display: block;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-box input {
            width: 100%;
            height: 46px;
            padding: 11px 14px;
            background: #ffffff;
            border: 1px solid var(--field-border);
            border-radius: 8px;
            font-size: 0.94rem;
            color: var(--text-main);
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .input-box input#password {
            padding-right: 44px;
        }

        .input-box input::placeholder {
            color: #94a3b8;
        }

        .input-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(180, 35, 45, 0.09);
        }

        .input-box #toggleBtn {
            position: absolute;
            right: 12px;
            font-size: 1.1rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: color 0.18s ease;
            padding: 5px;
        }

        .input-box #toggleBtn:hover {
            color: var(--primary);
        }

        .pin-input {
            letter-spacing: 0.45rem;
            text-align: center;
            font-weight: 700;
        }

        .submit-btn {
            margin-top: 2px;
            width: 100%;
            padding: 13px 18px;
            background: #202a3a;
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 0.94rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.18s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .submit-btn:hover {
            background: var(--primary);
        }

        .submit-btn:active {
            background: var(--primary-hover);
        }

        .submit-btn:focus-visible,
        .input-box input:focus-visible {
            outline: 3px solid rgba(180, 35, 45, 0.2);
            outline-offset: 2px;
        }

        #status-area {
            display: none;
            padding: 14px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
            line-height: 1.5;
            animation: none;
        }
        .msg-error { background: var(--error-bg); color: var(--error); border: 1px solid var(--error-border); }
        .msg-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success-border); }

        @media (max-width: 480px) {
            .login-card {
                padding: 28px 22px 24px;
            }
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="logo-container">
        <img class="school-logo" src="../assets/images/icons/granby_logo.png" alt="Granby Colleges of Science and Technology">
    </div>
    <h1>Super Admin Sign In</h1>
    <p class="subtitle">Sign in with your super administrator account.</p>
    
    <div id="status-area"></div>

    <div class="form-group">
        <form action="http://localhost/GCST_Track_System/actions/process_superadmin.php" method="POST" id="authForm" onsubmit="return validateLogin()">
            <div class="input-box">
                <label for="email">Account</label>
                <div class="input-wrapper">
                    <input type="text" name="email" id="email" placeholder="Username or Email" required autocomplete="username">
                </div>
            </div>

            <div class="input-box">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="password" placeholder="••••••••" required autocomplete="current-password">
                    <i class='bx bx-show' id="toggleBtn"></i>
                </div>
            </div>

            <div class="input-box">
                <label for="pin">Security PIN (4 Digits)</label>
                <div class="input-wrapper">
                    <input type="password" name="pin" id="pin" class="pin-input" placeholder="••••" maxlength="4" required pattern="\d{4}" inputmode="numeric" autocomplete="one-time-code">
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="submit-btn" id="loginBtn">
                    Sign In
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const statusArea = document.getElementById("status-area");
    const toggleBtn = document.getElementById("toggleBtn");
    const passwordField = document.getElementById("password");
    const pinInput = document.getElementById("pin");

    // Password visibility handler
    toggleBtn.addEventListener("click", () => {
        const isPass = passwordField.type === "password";
        passwordField.type = isPass ? "text" : "password";
        toggleBtn.classList.toggle("bx-show");
        toggleBtn.classList.toggle("bx-hide");
    });

    // Enforce numeric only on PIN
    pinInput.addEventListener("input", (e) => {
        e.target.value = e.target.value.replace(/[^0-9]/g, '');
    });

    function validateLogin() {
        const btn = document.getElementById("loginBtn");
        if (pinInput.value.length !== 4) {
            showLocalError("Security PIN must be exactly 4 digits.");
            return false;
        }
        btn.disabled = true;
        btn.innerHTML = "<i class='bx bx-loader-alt bx-spin' style='margin-right: 8px;'></i> Authenticating...";
        return true;
    }

    function showLocalError(msg) {
        statusArea.style.display = "block";
        statusArea.className = "msg-error";
        statusArea.innerText = msg;
    }

    // Handle incoming URL notification parameters
    const urlParams = new URLSearchParams(window.location.search);

    if (urlParams.has('error') || urlParams.has('success')) {
        statusArea.style.display = "block";
        const err = urlParams.get('error');
        statusArea.className = "msg-error";
        
        if (err === 'invalid') {
            statusArea.innerText = "Authentication failed. Invalid identifier or password.";
        } else if (err === 'invalid_pin') {
            statusArea.innerText = "Access Denied: The security PIN is incorrect.";
        } else if (err === 'locked') {
            statusArea.innerText = "Account Locked: Too many failed attempts.";
        } else if (err === 'missing') {
            statusArea.innerText = "All fields (Identifier, Password, and PIN) are required.";
        } else if (err === 'unauthorized') {
            statusArea.innerText = "Access Forbidden: Account inactive or suspended.";
        } else if (err === 'database') {
            statusArea.innerText = "System Error: Unable to reach authentication server. Try again later.";
        } else if (urlParams.get('success') === '1') {
            statusArea.className = "msg-success";
            statusArea.innerText = "Profile verified. Please login.";
        }
    }
</script>

</body>
</html>