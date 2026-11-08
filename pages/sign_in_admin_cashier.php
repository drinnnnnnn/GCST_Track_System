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
            --primary: #315d96;
            --text-dark: #172033;
            --text-muted: #667085;
            --field-border: #d9dee7;
            --button-bg: #202a3a;
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
                linear-gradient(rgba(242, 244, 247, 0.68), rgba(242, 244, 247, 0.68)),
                url('../assets/images/bg/Granby.jpg') center / cover no-repeat fixed;
        }

        .login-card {
            width: 100%;
            max-width: 410px;
            padding: 34px 36px 30px;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-top: 3px solid var(--primary);
            border-radius: 20px;
            box-shadow: 0 16px 40px rgba(23, 32, 51, 0.12);
            text-align: center;
        }

        .school-logo {
            display: block;
            width: 64px;
            height: 64px;
            object-fit: contain;
            margin: 0 auto 18px;
        }

        .login-card h1 {
            font-size: 1.9rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.04em;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .login-card p {
            color: var(--text-muted);
            font-size: 0.93rem;
            margin-bottom: 28px;
        }

        .input-box {
            position: relative;
            margin-bottom: 18px;
            text-align: left;
        }

        .input-box label {
            display: block;
            margin-bottom: 7px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475467;
        }

        .input-box input {
            width: 100%;
            height: 48px;
            padding: 12px 44px 12px 14px;
            background: #ffffff;
            border: 1px solid var(--field-border);
            border-radius: 10px;
            font-size: 0.96rem;
            color: var(--text-dark);
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .input-box input::placeholder {
            color: #98a2b3;
        }

        .input-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(49, 93, 150, 0.1);
        }

        .input-box i {
            position: absolute;
            right: 14px;
            top: 40px;
            font-size: 1.15rem;
            color: #8792a2;
            cursor: default;
        }

        .input-box i.tooltip-icon {
            cursor: pointer;
        }

        .input-box i.tooltip-icon:hover {
            color: var(--primary);
        }

                .tooltip-icon::after {
                    content: attr(data-tooltip);
                    position: absolute;
                    top: -30px;
                    right: -8px;
                    background: rgba(15, 23, 42, 0.92);
                    color: #fff;
                    font-size: 0.7rem;
                    padding: 6px 9px;
                    border-radius: 8px;
                    opacity: 0;
                    visibility: hidden;
                    transform: translateY(4px);
                    transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
                    pointer-events: none;
                    white-space: nowrap;
                }

                .tooltip-icon:hover::after {
                    opacity: 1;
                    visibility: visible;
                    transform: translateY(0);
                }

        .submit-btn {
            width: 100%;
            margin-top: 6px;
            padding: 14px 18px;
            background: var(--button-bg);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 0.98rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.18s ease;
        }

        .submit-btn:hover {
            background: var(--primary);
        }

        .submit-btn:focus-visible,
        .input-box input:focus-visible {
            outline: 3px solid rgba(49, 93, 150, 0.22);
            outline-offset: 2px;
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

        @media (max-width: 480px) {
            body {
                padding: 16px;
            }

            .login-card {
                padding: 28px 22px 24px;
            }
        }
    </style>
</head>
<body>

<div class="login-card">
    <img class="school-logo animate-item" src="../assets/images/icons/granby_logo.png" alt="Granby Colleges of Science and Technology">

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

    const pinInput = document.getElementById("pin");
    pinInput.addEventListener("input", () => {
        pinInput.value = pinInput.value.replace(/\D/g, "").slice(0, 4);
    });

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

        } else if (errorCode === 'invalidpin') {
            statusArea.classList.add('msg-error');
            statusArea.innerText = "PIN must contain exactly 4 digits.";
            statusArea.setAttribute('data-tooltip', 'Enter numbers only for the 4-digit PIN.');

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