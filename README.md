# GCST_Track_System

Super Admin
http://localhost/GCST_Track_System/pages/sign_in_superadmin.php

Admin/Cashier
http://localhost/GCST_Track_System/pages/sign_in_admin_cashier.php

User
http://localhost/GCST_Track_System/pages/user/user_home.php

## Daily low-stock email

The daily low-stock report lists products with fewer than 10 units in stock and
sends it to every active account with the `admin` role. It uses the configured
SMTP settings and records delivery attempts in `email_notifications`.

On Windows/XAMPP, create a daily task in Task Scheduler:

- **Program/script:** `C:\xampp\php\php.exe`
- **Arguments:** `C:\xampp\htdocs\GCST_Track_System\actions\send_daily_low_stock_notification.php`
- **Start in:** `C:\xampp\htdocs\GCST_Track_System`

Set the task trigger to run daily at 9:00 AM local time, or choose another
preferred time. SMTP credentials must be configured in the application's
`.env` file.