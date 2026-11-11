<?php $isModal = ($_GET['modal'] ?? '') === '1'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Sign Up</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --primary: #315d96;
            --primary-glow: rgba(49, 93, 150, 0.15);
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --field-border: #d7dce4;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
            text-decoration: none;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background:
                linear-gradient(rgba(242, 244, 247, 0.68), rgba(242, 244, 247, 0.68)),
                url('../../assets/images/bg/Granby.jpg') center / cover no-repeat fixed;
            padding: 24px;
            overflow-x: hidden;
        }

        body.modal-embed {
            height: 100vh;
            min-height: 0;
            padding: 0;
            align-items: center;
            background: transparent;
            overflow: hidden;
        }

        .signup-card {
            width: 100%;
            max-width: 760px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.85);
            border-top: 3px solid var(--primary);
            border-radius: 20px;
            box-shadow: 0 16px 40px rgba(23, 32, 51, 0.12);
            padding: 30px 34px 26px;
            position: relative;
        }

        .modal-embed .signup-card {
            display: flex;
            flex-direction: column;
            max-height: 100vh;
            overflow: hidden;
        }

        .modal-embed .signup-card > form {
            display: flex;
            flex: 1 1 auto;
            flex-direction: column;
            min-height: 0;
        }

        .embedded-signup-close {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 2;
            display: grid;
            place-items: center;
            width: clamp(32px, 2.75vw, 36px);
            height: clamp(32px, 2.75vw, 36px);
            border: 0;
            border-radius: 0;
            background: transparent;
            color: #475569;
            font-size: 23px;
            line-height: 1;
            cursor: pointer;
            box-shadow: none;
        }

        .embedded-signup-close:hover {
            color: #0f172a;
        }

        .form-header {
            text-align: center;
            margin-bottom: 22px;
        }

        .school-logo {
            display: block;
            width: 60px;
            height: 60px;
            object-fit: contain;
            margin: 0 auto 12px;
        }

        h1 {
            font-size: 25px;
            font-weight: 700;
            color: var(--text-dark);
            letter-spacing: -0.04em;
            margin-bottom: 5px;
        }

        .subtitle {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .register-scroll {
            max-height: min(52vh, 440px);
            overflow-y: auto;
            padding: 0 8px 0 0;
            margin-bottom: 16px;
        }

        .modal-embed .register-scroll {
            flex: 1 1 auto;
            min-height: 0;
            max-height: none;
        }

        .register-scroll::-webkit-scrollbar { width: 5px; }
        .register-scroll::-webkit-scrollbar-thumb { 
            background: #cbd5e1;
            border-radius: 8px;
        }

        .input-box {
            position: relative;
            margin-bottom: 12px;
        }

        .input-box input {
            width: 100%;
            height: 46px;
            padding: 11px 44px 11px 14px;
            background: #ffffff;
            border: 1px solid var(--field-border);
            border-radius: 9px;
            font-size: 14px;
            color: var(--text-dark);
            font-weight: 500;
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .input-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .input-box input[type="file"] {
            padding: 6px 40px 6px 10px;
            font-size: 13px;
        }

        .input-box input[type="file"]::file-selector-button {
            background: #eef3f8;
            color: var(--text-dark);
            border: 1px solid var(--field-border);
            padding: 6px 11px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 10px;
        }

        .input-box input[type="file"]::file-selector-button:hover {
            background: #e4edf7;
        }

        .pwd-upload-section {
            display: none;
            padding: 12px;
            background: #f5f8fb;
            border-radius: 9px;
            border: 1px solid #dfe7ef;
        }

        .pwd-upload-note {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 10px;
            line-height: 1.45;
        }

        .pwd-upload-group {
            margin-bottom: 12px;
        }

        .pwd-upload-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 5px;
        }

        .preview-box {
            display: none;
            margin-top: 8px;
        }

        .preview-box img {
            width: 100%;
            max-height: 220px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--field-border);
        }

        .register-scroll select {
            height: 46px !important;
            padding-left: 14px !important;
            padding-right: 40px !important;
            background-color: #ffffff !important;
            border: 1px solid var(--field-border) !important;
            border-radius: 9px !important;
            box-shadow: none !important;
            font-size: 14px !important;
            color: var(--text-dark) !important;
        }

        .register-scroll select:focus {
            outline: none !important;
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 3px var(--primary-glow) !important;
        }

        .register-scroll > .relative {
            margin-bottom: 12px !important;
        }

        .input-box i {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 19px;
            color: #8792a2;
        }

        .toggle-password { cursor: pointer; z-index: 5; }
        .toggle-password:hover { color: var(--primary); }

        .btn {
            width: 100%;
            padding: 14px 16px;
            background: #202a3a;
            color: white;
            border: none;
            border-radius: 9px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.18s ease;
        }

        .btn:hover {
            background: var(--primary);
        }

        .signin-link {
            text-align: center;
            margin-top: 18px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .signin-link a {
            color: var(--primary);
            font-weight: 600;
        }

        /* Toast / Messages */
        .floating-message {
            position: fixed;
            top: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            min-width: 280px;
            background: var(--primary);
            color: white;
            padding: 13px 20px;
            border-radius: 10px;
            font-weight: 600;
            box-shadow: 0 8px 22px rgba(23, 32, 51, 0.14);
            opacity: 0;
            transition: 0.4s;
            z-index: 1000;
        }

        .floating-message.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .floating-message.error { background: #dc2626; }

        .animate-item {
            opacity: 1;
            animation: none;
        }

        @media (max-width: 600px) {
            body {
                padding: 14px;
            }

            .signup-card {
                padding: 24px 18px 20px;
            }

            .register-scroll {
                max-height: 52vh;
            }

        }
    </style>
</head>

<body class="<?= $isModal ? 'modal-embed' : '' ?>">
    <div id="floating-message" class="floating-message" role="status" aria-live="polite"></div>

    <div class="signup-card">
        <?php if ($isModal): ?>
            <button type="button" class="embedded-signup-close" aria-label="Close sign-up form" onclick="window.parent.closeSignupModal()">×</button>
        <?php endif; ?>
        <form action="../../actions/sign_up.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm(this)">
            <!-- Hint for PHP maximum file size (5MB) -->
            <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
            <?php if ($isModal): ?>
                <input type="hidden" name="signup_return" value="modal">
            <?php endif; ?>
            
            <div class="form-header header-anim animate-item">
                <img class="school-logo" src="../../assets/images/icons/granby_logo.png" alt="Granby Colleges of Science and Technology">
                <h1>Sign Up</h1>
                <p class="subtitle">Create a new account and manage your library profile easily.</p>
            </div>

            <div class="register-scroll form-anim animate-item">
                <div class="input-box">
                    <input type="text" name="student_id" placeholder="Student ID (GC-XXXXXX)" autocapitalize="characters" spellcheck="false" oninput="this.value = this.value.toUpperCase()" required pattern="^GC-\d{6}$" title="Format: GC-XXXXXX">
                    <i class='bx bxs-id-card'></i>
                </div>

                <div class="input-box">
                    <input type="text" name="last_name" placeholder="Last Name" required>
                    <i class='bx bxs-user'></i>
                </div>

                <div class="input-box">
                    <input type="text" name="first_name" placeholder="First Name" required>
                    <i class='bx bxs-user'></i>
                </div>

                <div class="input-box">
                    <input type="text" name="middle_name" placeholder="Middle Name (optional)">
                    <i class='bx bxs-user'></i>
                </div>

                <div class="relative w-full mb-6">
                    <select name="sex" required 
                        class="w-full h-12 pl-4 pr-12 bg-white border-0 ring-1 ring-gray-200 rounded-xl appearance-none outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all text-gray-500 cursor-pointer shadow-sm">
                        <option value="" disabled selected class="text-gray-500">Select Gender</option>
                        <option value="Male" class="text-gray-500">Male</option>
                        <option value="Female" class="text-gray-500">Female</option>
                    </select>
                    <i class='bx bx-male-female absolute right-4 top-1/2 -translate-y-1/2 text-lg text-gray-400 pointer-events-none'></i>
                </div>

                <div class="relative w-full mb-6">
                    <select 
                        name="course" 
                        required 
                        class="w-full h-12 pl-4 pr-12 bg-white border-0 ring-1 ring-gray-200 rounded-xl appearance-none outline-none focus:ring-2 focus:ring-blue-500 transition-all text-gray-500 cursor-pointer shadow-sm">
                        <option value="" disabled selected class="text-gray-500">Select Course/Program</option>
                        <option value="BS Information Technology">BS Information Technology</option>
                        <option value="BS Computer Science">BS Computer Science</option>
                        <option value="BS Tourism Management">BS Tourism Management</option>
                        <option value="BS Business Administration">BS Business Administration</option>
                        <option value="B Elementary Education">B Elementary Education</option>
                        <option value="B Secondary Education">B Secondary Education</option>
                        <option value="BS Criminology">BS Criminology</option>
                        <option value="BS Accountancy">BS Accountancy</option>
                    </select>
                    <i class='bx bxs-graduation absolute right-4 top-1/2 -translate-y-1/2 text-lg text-gray-400 pointer-events-none'></i>
                </div>

                <div class="relative w-full mb-6">
                    <select name="department" required 
                        class="w-full h-12 pl-4 pr-12 bg-white border-0 ring-1 ring-gray-200 rounded-xl appearance-none outline-none focus:ring-2 focus:ring-blue-500 transition-all text-gray-500 cursor-pointer shadow-sm invalid:text-gray-400">
                        <option value="" disabled selected class="text-gray-500">Select Department</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Computer Science">Computer Science</option>
                        <option value="Tourism Management">Tourism Management</option>
                        <option value="Business Administration">Business Administration</option>
                        <option value="Elementary Education">Elementary Education</option>
                        <option value="Secondary Education">Secondary Education</option>
                        <option value="Criminology">Criminology</option>
                        <option value="Accountancy">Accountancy</option>
                    </select>
                    <i class='bx bxs-buildings absolute right-4 top-1/2 -translate-y-1/2 text-lg text-gray-400 pointer-events-none'></i>
                </div>

                <div class="relative w-full mb-6">
                    <select name="year_level" required 
                        class="w-full h-12 pl-4 pr-12 bg-white border-0 ring-1 ring-gray-200 rounded-xl appearance-none outline-none focus:ring-2 focus:ring-blue-500 transition-all text-gray-500 cursor-pointer shadow-sm invalid:text-gray-400">
                        <option value="" disabled selected class="text-gray-400">Select Year Level</option>
                        <option value="1" class="text-gray-500">1st Year</option>
                        <option value="2" class="text-gray-500">2nd Year</option>
                        <option value="3" class="text-gray-500">3rd Year</option>
                        <option value="4" class="text-gray-500">4th Year</option>
                    </select>
                    <i class='bx bxs-layer absolute right-4 top-1/2 -translate-y-1/2 text-lg text-gray-400 pointer-events-none'></i>
                </div>

                <div class="relative w-full mb-6">
                    <input type="text" name="contact_number" placeholder="Contact Number (e.g. 09123456789)" required pattern="\d{11}" maxlength="11"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                        class="w-full h-12 pl-4 pr-12 bg-white border-0 ring-1 ring-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 transition-all text-gray-500 placeholder-gray-400 shadow-sm"> 
                    <i class='bx bxs-phone absolute right-4 top-1/2 -translate-y-1/2 text-lg text-gray-400 pointer-events-none'></i>
                </div>

                <div class="input-box">
                    <input type="text" name="address" placeholder="Address" required>
                    <i class='bx bxs-map'></i>
                </div>

                <div class="input-box" style="margin-bottom: 12px;">
                    <label style="display:flex; align-items:center; gap:10px; color:var(--text-muted); font-size:14px; font-weight:600; cursor:pointer;">
                        <input type="checkbox" id="pwdCheckbox" name="is_pwd" value="1" style="width:18px; height:18px; accent-color: var(--primary);">
                        <span>Yes, I am a Person With Disability</span>
                    </label>
                </div>

                <div id="pwdSection" class="input-box pwd-upload-section">
                    <p class="pwd-upload-note">Please ensure your PWD ID is clear and valid for verification.</p>
                    <div class="pwd-upload-group">
                        <label for="pwd_front">PWD ID Card (Front)</label>
                        <input type="file" id="pwd_front" name="pwd_front" accept=".jpg,.jpeg,.png" aria-label="PWD ID Card Front">
                        <div class="preview-box" id="pwdFrontPreviewBox">
                            <img id="pwdFrontPreview" alt="PWD front preview">
                        </div>
                    </div>
                    <div class="pwd-upload-group">
                        <label for="pwd_back">PWD ID Card (Back)</label>
                        <input type="file" id="pwd_back" name="pwd_back" accept=".jpg,.jpeg,.png" aria-label="PWD ID Card Back">
                        <div class="preview-box" id="pwdBackPreviewBox">
                            <img id="pwdBackPreview" alt="PWD back preview">
                        </div>
                    </div>
                </div>

                <div class="input-box">
                    <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px; margin-left: 5px;">Proof: School ID (Picture)</p>
                    <input type="file" name="school_id_pic" accept=".jpg,.jpeg,.png,.pdf" required>
                    <i class='bx bxs-image'></i>
                </div>

                <div class="input-box">
                    <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px; margin-left: 5px;">Proof: Registration Form</p>
                    <input type="file" name="reg_form" accept=".pdf,.jpg,.jpeg,.png" required>
                    <i class='bx bxs-file-pdf'></i>
                </div>

                <div class="input-box">
                    <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px; margin-left: 5px;">Proof: Payment Scheme</p>
                    <input type="file" name="payment_scheme" accept=".pdf,.jpg,.jpeg,.png" required>
                    <i class='bx bxs-credit-card'></i>
                </div>

                <div class="input-box">
                    <input type="email" name="email" placeholder="Email (example@email.com)" required>
                    <i class='bx bxs-envelope'></i>
                </div>

                <div class="input-box">
                    <input type="password" name="password" id="password" placeholder="Password (min 8 chars, 1 special)" required pattern="^(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$">
                    <i class='bx bx-show toggle-password' data-target="password"></i>
                </div>

                <div class="input-box">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                    <i class='bx bx-show toggle-password' data-target="confirm_password"></i>
                </div>
            </div>
            
            <button type="submit" class="btn btn-anim animate-item">Register Account</button>

            <div class="signin-link footer-anim animate-item">
                Already have an account? <a href="../../pages/sign_in.php" <?= $isModal ? 'onclick="parent.openLoginModalFromSignup(); return false;"' : '' ?>>Go to Sign In</a>
            </div>
        </form>
    </div>

    <script>
        const floatingMessage = document.getElementById("floating-message");
        const maxSizeBytes = 5 * 1024 * 1024;

        function showToast(message, isError = false) {
            floatingMessage.textContent = message;
            floatingMessage.classList.toggle("error", isError);
            floatingMessage.classList.add("show");
            setTimeout(() => floatingMessage.classList.remove("show"), 4000);
        }

        function bindImagePreview(inputId, previewId, previewBoxId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const previewBox = document.getElementById(previewBoxId);
            if (!input || !preview || !previewBox) return;

            let objectUrl = null;
            input.addEventListener('change', function () {
                if (objectUrl) URL.revokeObjectURL(objectUrl);
                const file = this.files && this.files[0];
                if (!file) {
                    previewBox.style.display = 'none';
                    preview.removeAttribute('src');
                    return;
                }

                objectUrl = URL.createObjectURL(file);
                preview.src = objectUrl;
                previewBox.style.display = 'block';
            });
        }

        // URL Status Handling
        document.addEventListener("DOMContentLoaded", function () {
            const params = new URLSearchParams(window.location.search);
            const status = params.get("status");

            if (status === "success") showToast("Successfully Registered! Please wait for account verification. You will receive an email notification once your account is verified.");
            else if (status === "exists") showToast("Account already exists.", true);
            else if (status === "nomatch") showToast("Passwords do not match.", true);
            else if (status === "weak_password") showToast("Password must be at least 8 characters and include a special character.", true);
            else if (status === "invalid_email") showToast("Please enter a valid email address.", true);
            else if (status === "too_large") showToast("One or more files exceed the 5MB limit.", true);
            else if (status === "invalid_file_type") showToast("Invalid file type. Only PDF, JPG, and PNG are allowed.", true);
            else if (status === "upload_failed") showToast("Document upload failed. Please try again.", true);
            else if (status === "invalid") showToast("Please check your form inputs.", true);
            else if (status === "error") showToast("We could not process your request right now. Please try again later.", true);

            const pwdCheckbox = document.getElementById('pwdCheckbox');
            const pwdSection = document.getElementById('pwdSection');
            if (pwdCheckbox && pwdSection) {
                pwdSection.style.display = pwdCheckbox.checked ? 'block' : 'none';
                pwdCheckbox.addEventListener('change', function () {
                    pwdSection.style.display = this.checked ? 'block' : 'none';
                });
            }

            bindImagePreview('pwd_front', 'pwdFrontPreview', 'pwdFrontPreviewBox');
            bindImagePreview('pwd_back', 'pwdBackPreview', 'pwdBackPreviewBox');
        });

        function validateRequiredFiles(form) {
            const requiredFileInputs = form.querySelectorAll('input[type="file"][required]');
            for (const input of requiredFileInputs) {
                if (!input.files || !input.files[0]) {
                    showToast("All required document uploads must be selected.", true);
                    return false;
                }
            }
            return true;
        }

        function validateForm(form) {
            const password = form.password.value;
            const confirm = form.confirm_password.value;
            const passwordPattern = /^(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/;
            const contactNumber = form.contact_number.value.trim();

            if (!passwordPattern.test(password)) {
                showToast("Password must be at least 8 characters and include a special character.", true);
                return false;
            }

            if (password !== confirm) {
                showToast("Passwords do not match!", true);
                return false;
            }

            if (!/^\d{11}$/.test(contactNumber)) {
                showToast("Contact number must be 11 digits.", true);
                return false;
            }

            if (!validateRequiredFiles(form)) {
                return false;
            }

            const pwdChecked = form.querySelector('#pwdCheckbox')?.checked;
            if (pwdChecked) {
                const pwdInputs = ['pwd_front', 'pwd_back'];
                for (const inputName of pwdInputs) {
                    const input = form.elements[inputName];
                    if (!input.files || !input.files[0]) {
                        showToast("PWD ID card front and back are required when PWD option is selected.", true);
                        return false;
                    }

                    const file = input.files[0];
                    const fileExt = file.name.split('.').pop().toLowerCase();
                    const allowedImageExt = ['jpg', 'jpeg', 'png'];

                    if (file.size > maxSizeBytes) {
                        showToast(`"${file.name}" exceeds the 5MB limit.`, true);
                        return false;
                    }
                    if (!allowedImageExt.includes(fileExt)) {
                        showToast(`"${file.name}" is not a valid format (JPG, PNG only).`, true);
                        return false;
                    }
                }
            }

            const allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
            const fileInputs = form.querySelectorAll('input[type="file"]');

            for (let input of fileInputs) {
                if (input.files && input.files[0]) {
                    const file = input.files[0];
                    const fileExt = file.name.split('.').pop().toLowerCase();

                    if (file.size > maxSizeBytes) {
                        showToast(`"${file.name}" exceeds the 5MB limit.`, true);
                        return false;
                    }
                    if (!allowedExtensions.includes(fileExt)) {
                        showToast(`"${file.name}" is not a valid format (JPG, PNG, PDF only).`, true);
                        return false;
                    }
                }
            }
            return true;
        }

        // Password Toggle Logic
        document.querySelectorAll('.toggle-password').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const input = document.getElementById(this.getAttribute('data-target'));
                const isPass = input.type === 'password';
                input.type = isPass ? 'text' : 'password';
                this.classList.toggle('bx-show', !isPass);
                this.classList.toggle('bx-hide', isPass);
            });
        });

        if (window.parent !== window) {
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && typeof window.parent.closeSignupModal === 'function') {
                    window.parent.closeSignupModal();
                }
            });
        }
    </script>
</body>
</html>