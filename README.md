# FindBack

### A Campus Lost & Found Portal with Smart Matching and Live Alerts

**FindBack** is a centralized, database-driven web application designed to help university students, faculty, and administrators report, discover, match, and recover lost and found belongings.

---

## Overview

In university campuses, misplaced items are an everyday reality. Students, faculty, and campus staff frequently misplace critical belongings such as:
* **Student ID Cards & Badges**
* **Wallets & Purses**
* **Calculators & Technical Equipment**
* **Textbooks & Course Notes**
* **Smartphones & Accessories**
* **Backpacks & Laptops**
* **Hostel & Vehicle Keys**
* **Wearables & Personal Belongings**

**FindBack** provides a structured, centralized workflow for reporting, discovering, matching, and recovering lost property across university departments, buildings, and campus facilities.

---

## Problem Statement

Historically, university campuses have relied on disorganized, informal channels to handle lost property:
* **Crowded WhatsApp & Telegram Groups:** Urgent posts get buried within hours under hundreds of casual messages.
* **Ad-Hoc Class Announcements:** Limited in reach and rarely seen by students in other departments.
* **Physical Notice Boards:** Static, slow to update, and inaccessible to students off-campus.
* **Word of Mouth & Security Bins:** Items accumulate in security desks and reception bins without clear identification.

These decentralized methods lead to low recovery rates, duplicate search efforts, privacy issues, and misplaced items remaining permanently unreturned.

**FindBack** centralizes this process by providing a unified portal where reports are cataloged, verified by administrators, automatically analyzed for potential cross-matches, and tracked until returned to their verified owners.

---

## Features

* **Authentication & Role-Based Access:** Secure student registration, password hashing (`bcrypt`), and administrator privilege separation.
* **Lost Item Reporting:** Intuitive workflow for filing lost belongings with category, incident date, campus landmark tagging, and photo attachment.
* **Found Item Reporting:** Matching workflow for campus finders to submit discovered items and custody locations (e.g. Security Desk, Department Office).
* **Multi-Token Search:** Keyword search across titles, descriptions, and campus locations without requiring exact phrases.
* **Category & Status Filtering:** Combinable filters by category, status (`active`, `recovered`, `closed`), and chronological sorting.
* **Item Details View:** Comprehensive listing view with status badges, verified details, timestamps, and secure inquiry triggers.
* **Secure Image Uploads:** Strict MIME-type checking, extension whitelisting (`.jpg`, `.jpeg`, `.png`, `.webp`), 5MB size limit, randomized naming, and direct script execution prevention.
* **Personal Reports Dashboard ("My Reports"):** Dedicated interface for students to manage submissions, view admin review status, track recovery, and see rejection feedback.
* **Smart / Explainable Matching:** Algorithmic comparison engine matching active `lost` reports against active `found` reports.
* **Live Alerts & Notifications:** Real-time polling (`api/notifications_poll.php`) alerting users to verifications, new matches, and inquiries with duplicate toast suppression.
* **Recovery Workflow:** Multi-step handshake tracking an item from initial report through moderation, contact request, and verified recovery.
* **Admin Verification & Moderation:** Administrative dashboard to verify submissions, reject inappropriate reports with mandatory logged reasons, and soft-delete listings.
* **Administrative Audit Logs:** Persistent database tracking of all moderator actions (`VERIFY_REPORT`, `REJECT_REPORT`, `DELETE_REPORT`) with timestamps and administrator references.
* **Data-Driven Analytics Dashboard:** Five Chart.js visualizations driven directly by MySQL aggregate queries with empty-state handling.

---

## Smart Matching Engine

FindBack includes a deterministic, explainable similarity scoring engine designed to bridge the gap between finders and seekers.

### Algorithmic Comparison Model
* **Opposite-Type Comparison:** The engine strictly compares `lost` items against `found` items (and vice versa). Same-type comparisons (`lost` $\leftrightarrow$ `lost`) are never performed.
* **Deterministic Rules (No Black-Box AI):** FindBack uses transparent, local string algorithms, campus landmark normalization, and temporal proximity scoring.

### 5-Factor Scoring Weights
Matches are evaluated on a 100-point scale across five deterministic factors:

| Factor | Weight | Evaluation Method |
|---|---|---|
| **Category Match** | **30%** | Exact match between categorized taxonomy tiers (e.g., *Electronics & Gadgets*). |
| **Item Name / Title** | **25%** | Evaluates token overlap, word containment, and character-level similarity (e.g., *"Casio fx-991EX"* $\leftrightarrow$ *"Casio Calculator"*). |
| **Description Keywords** | **20%** | Filters common campus filler words and measures shared distinctive descriptive keywords (*black*, *scratch*, *fountain*, *lanyard*). |
| **Location Proximity** | **15%** | Standardizes campus landmarks and building zones (*CSE Block*, *Library 2nd Floor*, *Cafeteria*, *Auditorium*). |
| **Date Proximity** | **10%** | Proximity curve rewarding reports filed on the same day ($10/10$) down to zero after three weeks. |

### Confidence Levels
* **Strong Potential Match:** Score $\ge 80\%$
* **Possible Match:** Score $60\% - 79\%$
* Matches below $60\%$ are filtered out to avoid spam and alert fatigue.

### Explainable Match Card Example
```text
Potential Match — 88% (Strong Potential Match)

[✓] Category Match (30/30)    : Exact Category Match (Books & Stationery)
[✓] Item Name (19/25)         : Highly similar item name (blue, parker, pen)
[✓] Description (15/20)       : Shared description keywords: blue, parker, fountain, pen
[✓] Location Proximity (14/15): Same campus building / block zone (LIBRARY)
[✓] Date Proximity (10/10)    : Reported on the exact same date
```

---

## Recovery Lifecycle

FindBack models the physical campus item recovery as an explicit state machine:

```text
       [Student Submits Report]
                  │
                  ▼
         Pending Verification
           /              \
    (Admin Rejects)   (Admin Approves)
         /                  \
        ▼                    ▼
     Rejected          Verified Active
                             │
                             ├──────────────────────────┐
                             │ (Engine Compares)        │ (No Match Yet)
                             ▼                          ▼
                     Potential Match              Active Listing
                             │                          │
                             ▼                          ▼
                    Contact / Handshake         Direct Student Claim
                             │                          │
                             └────────────┬─────────────┘
                                          │
                                          ▼
                                   Item Handover
                                          │
                                          ▼
                                  Marked Recovered
                           (Timestamp & User Logged)
                                          │
                                          ▼
                                  Resolved History
```

* **Permission Guard:** Only the verified owner or an administrator can mark an item recovered.
* **Audit Trail:** Recoveries record both the exact timestamp (`recovered_at`) and the user ID (`recovered_by`).
* **Match Resolution:** Marking an item recovered automatically updates corresponding records in `potential_matches` to `resolved`.

---

## Technology Stack

* **Backend:** PHP 8.0+ (PDO, Session, JSON, Fileinfo, OpenSSL)
* **Database:** MySQL 5.7+ / MariaDB 10.4+ (InnoDB engine, `utf8mb4_unicode_ci` charset)
* **Frontend:** HTML5, CSS3, JavaScript (Vanilla ES6+), Bootstrap 5, FontAwesome 6, Chart.js
* **Configuration:** Zero-dependency environment loader (`config/env.php`) supporting `.env` and system environment variables
* **Web Server:** Apache (mod_rewrite, mod_headers) or PHP Built-in Server

---

## Installation Guide (Local Development)

### Prerequisites
* [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP 8.0+) or standalone PHP and MySQL.
* Web browser (Chrome, Firefox, Edge, Safari).
* Git.

### Step-by-Step Local Setup

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/tejasreeguntipalli1-tech/FindBack.git
   cd FindBack
   ```

2. **Configure Environment:**
   Copy the example environment file:
   ```bash
   cp .env.example .env
   ```
   *(For default XAMPP, default settings connect to `127.0.0.1`, user `root`, no password, database `fsd_lost_and_found`.)*

3. **Import Database:**
   * Open phpMyAdmin or your MySQL CLI.
   * Import `database.sql`:
     ```bash
     mysql -u root -p < database.sql
     ```
   * *Alternatively, run the automated setup script in your browser or terminal:*
     ```bash
     php setup_database.php
     ```

4. **Start the Application:**
   * **Via PHP Built-in Server (Recommended for testing):**
     ```bash
     php -S localhost:8000
     ```
     Open `http://localhost:8000` in your web browser.
   * **Via XAMPP Apache:**
     Place the project in `C:\xampp\htdocs\fsd_lost_and_found` (or `C:\xampp\htdocs\FindBack`) and navigate to `http://localhost/fsd_lost_and_found`.

---

## Production Deployment Guide

FindBack is architected for seamless deployment to Linux VPS, cPanel shared hosting, AWS EC2, or containerized environments.

### 1. Server Requirements
* Web Server: Apache 2.4+ (with `mod_rewrite`, `mod_deflate`, `mod_expires`) or Nginx.
* PHP: Version 8.0, 8.1, 8.2, or 8.3 with extensions: `pdo_mysql`, `mbstring`, `fileinfo`, `json`, `session`.
* MySQL: MariaDB 10.4+ or MySQL 5.7+ / 8.0+.
* SSL/TLS Certificate (HTTPS enabled).

### 2. Environment Configuration
Create a production `.env` file in the root directory (never commit this file to Git):
```ini
APP_NAME="FindBack"
APP_ENV="production"
APP_DEBUG=false
APP_URL="https://yourdomain.com"

DB_HOST="your-db-host.com"
DB_PORT=3306
DB_NAME="your_production_dbname"
DB_USER="your_production_dbuser"
DB_PASS="your_secure_db_password"
```

### 3. Database Migration
Import `database.sql` into your production database:
```bash
mysql -h your-db-host.com -u your_production_dbuser -p your_production_dbname < database.sql
```

### 4. File Permissions
Ensure the web server user (`www-data`, `apache`, or `nobody`) has write permissions to the upload storage directory:
```bash
chmod 755 uploads/
chmod 755 uploads/items/
```

### 5. Web Server Configuration

#### Apache
The included `.htaccess` in the root automatically:
* Disables directory browsing (`Options -Indexes`).
* Denies web access to `.env`, `.git`, `.gitignore`, and SQL dump files.
* Enables Gzip compression and browser caching headers for static assets.
* Blocks PHP script execution inside `uploads/` (`uploads/.htaccess`).

#### Nginx
If deploying with Nginx, use the following server configuration snippet:
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/FindBack;
    index index.php index.html;

    # Protect hidden and sensitive files
    location ~ /\.(env|git|htaccess) {
        deny all;
    }
    location ~* \.(sql)$ {
        deny all;
    }

    # Prevent script execution in uploads
    location ~* ^/uploads/.*\.php$ {
        deny all;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }
}
```

---

## Security

* **Database Queries:** 100% of SQL queries utilize PDO prepared statements with parameter binding to prevent SQL injection.
* **Cross-Site Scripting (XSS):** All dynamic browser output is sanitized via HTML entity encoding (`e()`).
* **Session Cookie Hardening:** Sessions automatically set `httponly: true`, `samesite: Lax`, and enforce `secure: true` when accessed over HTTPS.
* **Upload Isolation:** Executable files are blocked by strict MIME and extension whitelisting, and `uploads/.htaccess` disables PHP and CGI execution inside upload directories.
* **Credentials Security:** Secrets are never hardcoded. All database credentials and app secrets are loaded via environment variables or excluded `.env` files.

---

## Demo Credentials

The seeded database includes the following ready-to-use demo accounts:

| Role | Name | Email | Password | Description |
|---|---|---|---|---|
| **Administrator** | Campus Administrator | `admin@campus.edu` | `Admin@123` | Full access to moderation, analytics, user management, and activity logs. |
| **Student** | Alex Johnson | `alex@campus.edu` | `Student@123` | CS student account with existing lost item reports and smart matches. |
| **Student** | Sarah Smith | `sarah@campus.edu` | `Student@123` | ECE student account with found item reports. |
| **Student** | Rahul Sharma | `rahul@campus.edu` | `Student@123` | Mechanical Engineering student demo account. |
| **Student** | Priya Patel | `priya@campus.edu` | `Student@123` | IT student demo account. |

---

## Project Structure

```text
FindBack/
├── admin/                     # Administrator portal & moderation
│   ├── analytics.php          # 5-chart database analytics view
│   ├── dashboard.php          # Admin KPI cards & quick actions
│   ├── logs.php               # System activity audit logs
│   ├── manage_reports.php     # Report moderation (Verify/Reject/Delete)
│   └── users.php              # User directory management
├── api/                       # Asynchronous JSON endpoints
│   ├── analytics_data.php     # Real-time metrics & chart datasets
│   ├── check_duplicate.php    # Live duplicate pre-check
│   ├── mark_notification.php  # Notification read state handler
│   ├── notifications_poll.php # Real-time background polling
│   ├── respond_match.php      # Match resolution handler
│   └── submit_claim.php       # Inquiries & handoff messages
├── assets/                    # Static styling and scripts
│   ├── css/style.css          # Core styles & utility classes
│   └── js/
│       ├── analytics.js       # Chart.js initialization & fallbacks
│       ├── live_poll.js       # Background alert polling & toasts
│       └── main.js            # General DOM & modal interactions
├── auth/                      # Authentication flows
│   ├── login.php              # Secure login with role redirect
│   ├── logout.php             # Session termination
│   └── register.php           # Student registration
├── config/                    # Core configuration & engines
│   ├── db.php                 # Active database connection (PDO + Env)
│   ├── db.example.php         # Environment configuration template
│   ├── env.php                # Zero-dependency .env loader
│   ├── helpers.php            # Security & sanitization utilities
│   ├── matcher.php            # 5-factor smart matching engine
│   └── session.php            # Session initiation & state helpers
├── database/                  # Schema & migrations
│   └── campusfind.sql         # Database schema & demo seed
├── includes/                  # Reusable UI partials
│   ├── auth_guard.php         # Route authorization checks
│   ├── footer.php             # Unified HTML footer
│   └── header.php             # Navigation, alert drawer & badge counters
├── student/                   # Student portal
│   ├── claims.php             # Active inquiries & handoff status
│   ├── dashboard.php          # Student KPI cards & recent submissions
│   ├── matches.php            # Explainable matches & factor breakdown
│   ├── my_reports.php         # Report manager with recovery & rejection info
│   ├── profile.php            # Account profile settings
│   └── report_item.php        # Lost/Found submission with duplicate guard
├── uploads/                   # Upload storage
│   ├── items/                 # Item photos
│   ├── .gitkeep               # Directory structure preservation
│   └── .htaccess              # Direct script execution protection
├── .env.example               # Environment variables template
├── .gitignore                 # Git ignore rules
├── .htaccess                  # Apache production security & caching
├── database.sql               # Universal database dump for deployment
├── index.php                  # Public item browse & multi-filter search
├── item_details.php           # Comprehensive item view & claim trigger
├── LICENSE                    # MIT License
├── README.md                  # Project documentation
├── setup_database.php         # Automated database setup & seeder
├── test_e2e_advanced.php      # Comprehensive 31-point test suite
└── test_workflow.php          # Core workflow audit script
```

---

## License

This project is licensed under the [MIT License](LICENSE).
