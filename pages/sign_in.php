<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Sign In | GCST Tracking System</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #315d96;
            --primary-glow: rgba(49, 93, 150, 0.18);
            --text-dark: #1f2b45;
            --text-muted: #5b6c8f;
            --glass: rgba(255, 255, 255, 0.85);
            --overlay: rgba(31, 43, 69, 0.4);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
            text-decoration: none;
            list-style: none;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background:
                linear-gradient(rgba(242, 244, 247, 0.68), rgba(242, 244, 247, 0.68)),
                url('../assets/images/bg/Granby.jpg') center / cover no-repeat fixed;
            overflow-x: hidden;
            padding: 24px;
            color: var(--text-dark);
        }

        .container {
            position: relative;
            width: 100%;
            max-width: 410px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 16px 40px rgba(23, 32, 51, 0.12);
            padding: 34px 36px 30px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-top: 3px solid var(--primary);
            z-index: 10;
        }

        /* Global Modal Styling */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: var(--overlay);
            backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.active { display: flex; opacity: 1; }

        .modal-box {
            background: var(--glass);
            max-width: 450px;
            width: 100%;
            padding: 40px;
            border-radius: 32px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.1);
            text-align: center;
            transform: scale(0.8);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .modal-overlay.active .modal-box { transform: scale(1); }

        /* UI Elements */
        .form-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            margin-bottom: 26px;
            text-align: center;
        }

        .school-logo {
            display: block;
            width: 64px;
            height: 64px;
            object-fit: contain;
        }

        .header-icon {
            width: 80px; height: 80px;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #315d96, #4776ad);
            border-radius: 24px; color: #ffffff; font-size: 40px;
            box-shadow: 0 15px 35px var(--primary-glow);
            animation: iconPulse 3s infinite ease-in-out;
        }

        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .form-header h1 {
            font-size: 1.9rem;
            line-height: 1.2;
            letter-spacing: -0.04em;
        }

        .form-header p {
            margin-top: 5px;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .input-box { position: relative; margin-bottom: 18px; }

        .input-box input {
            width: 100%; height: 48px; padding: 12px 46px 12px 14px;
            background: #ffffff;
            border-radius: 10px; border: 1px solid #d9dee7;
            outline: none; font-size: 0.95rem; color: var(--text-dark);
            font-weight: 500; transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .input-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .input-box i { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); font-size: 20px; color: #8792a2; }
        .toggle-password { cursor: pointer; z-index: 10; }

        .btn {
            width: 100%; height: 50px;
            background: var(--text-dark); border-radius: 10px; border: none;
            cursor: pointer; font-size: 0.98rem; color: #fff; font-weight: 600;
            transition: background 0.18s ease;
            margin-top: 6px;
        }

        .btn:hover { background: var(--primary); }

        .signup-link { text-align: center; margin-top: 24px; font-size: 14px; color: var(--text-muted); }
        .signup-link a { color: var(--primary); font-weight: 700; cursor: pointer; }

        /* Animation Classes */
        .animate-item { opacity: 0; animation: fadeInUp 0.5s ease forwards; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .header-anim { animation-delay: 0.1s; }
        .input-anim-1 { animation-delay: 0.2s; }
        .input-anim-2 { animation-delay: 0.3s; }
        .btn-anim { animation-delay: 0.5s; }
        .footer-anim { animation-delay: 0.6s; }

        /* Floating Toast */
        .floating-message {
            position: fixed; top: 24px; left: 50%; transform: translateX(-50%) translateY(-20px);
            min-width: 300px; background: var(--text-dark); color: white;
            padding: 16px 24px; border-radius: 20px; font-weight: 600;
            text-align: center; opacity: 0; transition: all 0.4s ease;
            z-index: 3000; pointer-events: none;
            display: flex; align-items: center; justify-content: center; gap: 12px;
        }
        .floating-message.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .floating-message.error { background: #dc2626; }
        .floating-message i { font-size: 24px; }

        .info-highlight {
            background: rgba(255, 255, 255, 0.5); padding: 16px;
            border-radius: 16px; border: 1px dashed var(--primary);
            margin: 20px 0; font-weight: 600; font-size: 13px;
        }
    </style>
</head>

<body>
    <div id="floating-message" class="floating-message"></div>

    <div class="container">
        <!-- Main Login Form -->
        <form action="http://localhost/GCST_Track_System/actions/log_in.php" method="POST" onsubmit="return validateForm(this)">
            <div class="form-header header-anim animate-item">
                <img class="school-logo" src="../assets/images/icons/granby_logo.png" alt="Granby Colleges of Science and Technology">
                <div>
                    <h1>Sign In</h1>
                    <p>Enter your student credentials to continue</p>
                </div>
            </div>

            <div class="input-box input-anim-1 animate-item">
                <input type="text" name="student_id" placeholder="Student ID Number" required>
                <i class='bx bxs-id-card'></i>
            </div>

            <div class="input-box input-anim-2 animate-item">
                <input type="password" name="password" id="loginPass" placeholder="Password" required>
                <i class='bx bx-show toggle-password' id="toggleLoginPass"></i>
            </div>

            <div style="text-align: right; margin-bottom: 24px;" class="animate-item link-anim">
                <a href="javascript:void(0)" onclick="toggleModal('resetModal', true)" style="font-size: 14px; color: var(--primary); font-weight: 700;">Forgot Password?</a>
            </div>

            <button type="submit" class="btn btn-anim animate-item" id="submitBtn">Sign In</button>
            
            <div class="signup-link footer-anim animate-item">
                Don't have an account? <a href="http://localhost/GCST_Track_System/pages/superadmin/sign_up.php">Sign Up</a>
            </div>
        </form>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal-overlay" id="resetModal">
        <div class="modal-box">
            <div class="header-icon"><i class='bx bx-reset'></i></div>
            <h2>Reset Password</h2>
            <p id="reset-modal-desc" style="margin: 15px 0; color: var(--text-muted); font-size: 14px;">Enter your registered Email address to receive a verification code.</p>
            
            <div id="reset-step-1">
                <div class="input-box">
                    <input type="email" id="reset_identifier" placeholder="Registered Email Address" required>
                    <i class='bx bxs-envelope'></i>
                </div>
                <button type="button" class="btn" id="btnRequestCode" onclick="requestResetCode()">Send Verification Code</button>
            </div>

            <div id="reset-step-2" style="display:none;">
                <div class="input-box">
                    <input type="text" id="reset_otp" placeholder="6-Digit Code" maxlength="6" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="text-align: center; letter-spacing: 4px;">
                </div>
                <button type="button" class="btn" id="btnVerifyCode" onclick="verifyResetCode()">Verify Code</button>
            </div>

            <div id="reset-step-3" style="display:none;">
                <div class="input-box">
                    <input type="password" id="new_password" placeholder="New Password" required>
                    <i class='bx bx-show toggle-password' id="toggleResetPass"></i>
                </div>
                <button type="button" class="btn" id="btnUpdatePass" onclick="performPasswordReset()">Update Password</button>
            </div>

            <button type="button" class="btn" style="background: transparent; color: var(--text-muted); box-shadow: none;" onclick="toggleModal('resetModal', false)">Back to Sign In</button>
        </div>
    </div>

    <script>
        // Handle status messages from URL parameters
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const error = urlParams.get('error');
            const success = urlParams.get('success');
            if (error === 'invalid') {
                showToast("Incorrect credentials or password.", true);
            } else if (error === 'pending') {
                showToast("Access Denied: Your account is currently pending approval. Please check back later.", true);
            } else if (error === 'rejected') {
                showToast("Access Denied: Your registration request was rejected. Please contact the administrative office.", true);
            } else if (error === 'suspended') {
                showToast("Access Restricted: Your account has been suspended. Please contact support for assistance.", true);
            } else if (success === 'registered') {
                showToast("Account created successfully! Your registration is now pending review.", false);
            }
        });

        // Modal Control
        function toggleModal(modalId, show) {
            const modal = document.getElementById(modalId);
            if (show) {
                modal.classList.add('active');
            } else {
                modal.classList.remove('active');
            }
        }

        // Close when clicking outside the modal box
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
            }
        }

        // Toast Notifications
        function showToast(message, isError = false) {
            const toast = document.getElementById("floating-message");
            const iconClass = isError ? "bx bxs-error-circle" : "bx bxs-check-circle";
            toast.innerHTML = `<i class='${iconClass}'></i> <span></span>`;
            toast.querySelector('span').textContent = message;
            toast.className = isError ? "floating-message show error" : "floating-message show";
            setTimeout(() => toast.classList.remove("show"), 8000); // Increased to 8 seconds
        }

        // Secure Password Reset Logic
        let resetData = { email: '', code: '', step: 1 };

        // Try to restore reset state from session storage
        try {
            const saved = sessionStorage.getItem('resetContext');
            if (saved) resetData = JSON.parse(saved);
        } catch(e) { console.error("Error restoring session:", e); }

        async function requestResetCode() {
            const identifier = document.getElementById('reset_identifier').value;
            const btn = document.getElementById('btnRequestCode');
            if(!identifier) return showToast("Identification is required", true);

            try {
                btn.disabled = true;
                btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Processing...";
                const resp = await fetch('../actions/reset_request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ identifier })
                });
                const result = await resp.json();
                if(result.success) {
                    resetData.email = result.email;
                    resetData.step = 2;
                    sessionStorage.setItem('resetContext', JSON.stringify(resetData));
                    document.getElementById('reset-step-1').style.display = 'none';
                    document.getElementById('reset-step-2').style.display = 'block';
                    document.getElementById('reset-modal-desc').innerText = result.message;
                    showToast("Code Sent!");
                } else throw new Error(result.message);
            } catch(e) { showToast(e.message, true); }
            finally {
                btn.disabled = false;
                btn.innerText = "Send Verification Code";
            }
        }

        async function verifyResetCode() {
            const code = document.getElementById('reset_otp').value;
            const btn = document.getElementById('btnVerifyCode');
            if(code.length !== 6) return showToast("Enter the full 6-digit code", true);
            
            try {
                btn.disabled = true;
                btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Verifying...";
                const resp = await fetch('../actions/verify_reset_code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: resetData.email, code })
                });
                const result = await resp.json();
                if(result.success) {
                    resetData.code = code;
                    resetData.step = 3;
                    sessionStorage.setItem('resetContext', JSON.stringify(resetData));
                    document.getElementById('reset-step-2').style.display = 'none';
                    document.getElementById('reset-step-3').style.display = 'block';
                    document.getElementById('reset-modal-desc').innerText = "Verified! Set your new password.";
                } else throw new Error(result.message);
            } catch(e) { showToast(e.message, true); }
            finally {
                btn.disabled = false;
                btn.innerText = "Verify Code";
            }
        }

        async function performPasswordReset() {
            const password = document.getElementById('new_password').value;
            const btn = document.getElementById('btnUpdatePass');
            if(password.length < 6) return showToast("Password too short (min 6 chars)", true);

            try {
                btn.disabled = true;
                btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Updating...";
                const resp = await fetch('../actions/update_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        email: resetData.email, 
                        code: resetData.code,
                        password: password 
                    })
                });
                const result = await resp.json();
                if(result.success) {
                    sessionStorage.removeItem('resetContext');
                    showToast("Success! You can now sign in.");
                    setTimeout(() => location.reload(), 2000);
                } else throw new Error(result.message);
            } catch(e) { showToast(e.message, true); }
            finally {
                btn.disabled = false;
                btn.innerText = "Update Password";
            }
        }

        // UI Restoration logic on page load
        window.addEventListener('DOMContentLoaded', () => {
            if (resetData.email && resetData.step > 1) {
                toggleModal('resetModal', true);
                if (resetData.step === 2) {
                    document.getElementById('reset-step-1').style.display = 'none';
                    document.getElementById('reset-step-2').style.display = 'block';
                    document.getElementById('reset-modal-desc').innerText = "Enter the verification code sent to your email.";
                } else if (resetData.step === 3) {
                    document.getElementById('reset-step-1').style.display = 'none';
                    document.getElementById('reset-step-2').style.display = 'none';
                    document.getElementById('reset-step-3').style.display = 'block';
                    document.getElementById('reset-modal-desc').innerText = "Verified! Set your new password.";
                }
            }
        });

        // Login Validation
        function validateForm(form) {
            const btn = document.getElementById("submitBtn");
            if (!form.student_id.value || !form.password.value) {
                showToast("All fields are required", true);
                return false;
            }
            btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Authenticating...";
            return true;
        }

        // Password Toggle logic
        document.getElementById('toggleLoginPass').addEventListener('click', function() {
            const passInput = document.getElementById('loginPass');
            const isPass = passInput.type === 'password';
            passInput.type = isPass ? 'text' : 'password';
            this.classList.toggle('bx-show', !isPass);
            this.classList.toggle('bx-hide', isPass);
        });

        // New Password Toggle
        document.getElementById('toggleResetPass')?.addEventListener('click', function() {
            const passInput = document.getElementById('new_password');
            const isPass = passInput.type === 'password';
            passInput.type = isPass ? 'text' : 'password';
            this.classList.toggle('bx-show', !isPass);
            this.classList.toggle('bx-hide', isPass);
        });
    </script>
</body>
</html>