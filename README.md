# Neptunus (Shortlink Application)

Neptunus is a PHP-based shortlink management system that allows users to create, manage, and track short URLs. It features a modern, responsive dashboard and provides detailed visitor statistics.

## Technology Stack

- **Backend**: PHP 8.4+
- **Database**: SQLite (stored in `custom/database/database.db`)
- **Server**: Apache (required for `.htaccess` / `mod_rewrite`)
- **Frontend**: Vanilla JavaScript and CSS

## Key Features

- **Shortlink Management**: Create, edit, and duplicate short URLs.
- **Detailed Analytics**: Track visitor information including IP, user agent, and referral data.
- **User Authentication**: Secure login system with support for Google SSO.
- **Customizable UI**: Dark mode support and customizable fonts/styles.
- **Admin Dashboard**: Manage users, links, and system settings.
- **Backup & Restore**: Easily backup the database and restore from previous versions.

## Localhost Setup Instructions

### Prerequisites

- PHP 8.4 or higher
- Apache Web Server with `mod_rewrite` enabled
- SQLite support for PHP

### Installation Steps

1. **Clone the Repository**:

   ```bash
   git clone <repository-url>
   cd DAY059-neptunus
   ```

2. **Option A: WSL / Quick Start (PHP Internal Server)**:
   If you are running on WSL or want a quick setup, use the built-in PHP server:

   ```bash
   php -S localhost:8000 -t public
   ```

   _Note: This method might bypass some security headers defined in `.htaccess`._

3. **Option B: Apache Configuration**:
   - Point your web server's document root to the project directory.

4. **Database Permissions**:
   - Ensure the `custom/database/` directory and the `database.db` file within it are writable by the web server user (e.g., `www-data`).

5. **Verify Setup**:
   - Open your browser and navigate to your local development URL (e.g., `http://localhost/DAY059-neptunus`).
   - The application should automatically route you to the home or login page.

## Version

Current Version: 8.10.31

## Maintenance Scripts

- Database cleanup script: `api/sql/cleanup-tags-groups.php`
- Database migration script: `api/sql/migrate-db.php`
- Automatic backup runner (cron/task scheduler): `config/run-backup.php`

## Changelog

### 8.10.31

- Backup system redesigned to use snapshot directories in `custom/backup/snapshots/` with a lockfile to prevent concurrent writes.
- Each backup snapshot now includes:
  - `database.db`
  - `custom/` directory copy
  - `manifest.json` with SHA-256 checksums and metadata
- Retention policy implemented:
  - Keep latest 5 snapshots
  - Keep daily restore points for 14 days
  - Keep weekly restore points for 8 weeks
  - Keep monthly restore points for 6 months
- Backup state is written to `public/json/backup.json` and includes run history.
- Legacy top-level `scripts/` folder moved into existing logical folder `api/sql/`.
- Kept the three core bars sticky in the UI: logo/menu header, search/filter bar, and bulk actions bar.
