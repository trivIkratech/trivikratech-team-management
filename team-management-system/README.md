# Team Management System

A simple, secure, modern team management system built with **PHP 8+**, **MySQL**, and vanilla **HTML/CSS/JS**. Designed for easy deployment on **Hostinger Business Hosting**.

## Features

- **3 Roles**: Founder (full access), Manager (team management), Employee (attendance + tasks)
- **Attendance Management**: Check-in/out, working time calculation, history
- **Task Management**: Create, assign, track, update status
- **Role-Based Access Control**: Every page enforces permissions
- **Modern UI**: Dark theme, responsive, mobile-friendly
- **Security**: bcrypt passwords, CSRF protection, SQL injection prevention, XSS protection

---

## Deployment Guide (Hostinger Business Hosting)

### Step 1: Create a MySQL Database

1. Log in to **Hostinger hPanel**
2. Go to **Databases → MySQL Databases**
3. Create a new database (e.g., `team_management`)
4. Note down:
   - **Database Name**
   - **Database Username**
   - **Database Password**
   - **Host** (usually `localhost`)

### Step 2: Import the Database Schema

1. In hPanel, go to **Databases → phpMyAdmin**
2. Select your database
3. Click the **Import** tab
4. Upload the file: `database/schema.sql`
5. Click **Go** to run the import

### Step 3: Upload Files

1. In hPanel, go to **Files → File Manager** (or use FTP/SFTP)
2. Navigate to `public_html/` (or a subdirectory like `public_html/team-management-system/`)
3. Upload **all project files** maintaining the folder structure
4. Make sure the `.htaccess` file is uploaded (it may be hidden)

### Step 4: Configure Database Connection

1. Open `config/database.php`
2. Update these values with your Hostinger database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
```

### Step 5: Configure Base URL

1. Open `config/app.php`
2. Update the `BASE_URL` constant:

```php
// If deployed to root domain:
define('BASE_URL', '');

// If deployed to a subdirectory:
define('BASE_URL', '/team-management-system');
```

### Step 6: Set Founder Password

1. Visit: `https://yourdomain.com/team-management-system/database/setup.php`
2. This will set the correct bcrypt hash for the founder password
3. **IMPORTANT**: Delete `database/setup.php` immediately after running it

### Step 7: Login

1. Visit: `https://yourdomain.com/team-management-system/`
2. Login with:
   - **Email**: `founder@company.com`
   - **Password**: `Founder@123`
3. **Change your password** after first login (via User Management)

---

## Default Credentials

| Role    | Email               | Password     |
|---------|---------------------|--------------|
| Founder | founder@company.com | Founder@123  |

⚠️ **Change these immediately in production!**

---

## Folder Structure

```
team-management-system/
├── config/          # Database + app configuration
├── database/        # SQL schema + setup script
├── includes/        # Shared PHP components
├── auth/            # Login / Logout
├── founder/         # Founder pages (full access)
├── manager/         # Manager pages
├── employee/        # Employee pages
├── api/             # AJAX endpoints
├── assets/          # CSS, JS, images
├── uploads/         # File uploads (future)
├── index.php        # Entry point
└── .htaccess        # Security + caching
```

---

## Security Notes

- All passwords are hashed with `password_hash()` (bcrypt)
- All SQL queries use PDO prepared statements
- CSRF tokens protect all forms
- All output is escaped with `htmlspecialchars()`
- Session fixation is prevented with `session_regenerate_id()`
- `.htaccess` blocks direct access to config/includes/database directories
- Role-based access is enforced on every page

---

## Requirements

- PHP 8.0+ with PDO MySQL extension
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite enabled

---

## License

Internal company use only.
