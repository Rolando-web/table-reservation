# Project Structure

## Root Directory (Public Access)
- `index.php` - Landing/home page
- `login.php` - User authentication
- `register.php` - User registration
- `logout.php` - Session termination

## Admin Folder (`/admin/`)
- `admin_dashboard.php` - Admin control panel

## User Folder (`/user/`)
- `user_dashboard.php` - User main dashboard
- `generate_receipt.php` - Receipt generation for reservations

## Includes Folder (`/includes/`)
- `db.php` - Database connection and configuration
- `check_user.php` - Debugging utility for user management

## Assets Folder (`/assets/`)
- `css/` - Stylesheets
- `js/` - JavaScript files

## Notes
- All database connections now reference `includes/db.php`
- Admin files redirect to `../login.php`
- User files redirect to `../login.php`
- Logout button references `../logout.php` from subfolder pages
