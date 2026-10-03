# CampusFind

### Lost & Found Portal with Smart Matching and Live Alerts

---

## Overview

In university campuses, misplaced items are an everyday reality. Students, faculty, and campus staff frequently misplace critical belongings such as:
* **Student ID Cards & Badges**
* **Wallets & Purses**
* **Calculators & Technical Equipment**
* **Textbooks & Notebooks**
* **Smartphones & Accessories**
* **Backpacks & Bags**
* **Hostel & Vehicle Keys**
* **Wearables & Personal Electronics**

**CampusFind** is a centralized, database-driven web platform engineered to streamline the entire lifecycle of lost and found belongings—from initial submission and administrative verification to algorithmic potential matching, claim management, and verified recovery.

---

## Problem Statement

Historically, university campuses have relied on disorganized, informal channels to handle lost property:
* **Crowded WhatsApp & Telegram Groups:** Urgent posts get buried within hours under hundreds of chit-chat messages.
* **Ad-Hoc Class Announcements:** Limited in reach and rarely seen by students in other departments.
* **Physical Notice Boards:** Static, slow to update, and inaccessible to students off-campus.
* **Word of Mouth & Security Bins:** Bins accumulate unclaimed items indefinitely because finders and owners have no direct bridge.

These decentralized methods lead to low recovery rates, duplicate search efforts, identity verification issues, and misplaced items remaining permanently unreturned.

**CampusFind** solves this by offering a unified, structured portal where lost and found reports are cataloged, systematically verified by administrators, automatically analyzed for potential cross-matches, and tracked until returned to their verified owners.

---

## Key Features

* **Role-Based Authentication:** Secure sessions for Students and Campus Administrators with password hashing (`bcrypt`) and unauthorized access guards.
* **Lost & Found Reporting:** Clean submission workflow with category assignment, campus landmark tagging, incident date tracking, and optional photo attachment.
* **Search & Multi-Filter Querying:** Multi-token search across titles, descriptions, and locations combining category, status, and sorting filters.
* **Explainable Smart Matching:** Opposite-type comparison engine that identifies potential matches between active `lost` and `found` reports.
* **Transparent Factor Breakdown:** Every match displays a granular breakdown of contributing attributes rather than a black-box percentage.
* **Duplicate Submission Detection:** Real-time client-side checks and server-side guards preventing duplicate reports for the same incident.
* **Live Alert Polling:** Periodic background polling delivering instant notifications for verification updates, new matches, and contact requests with duplicate toast suppression.
* **Direct Contact & Claim Workflow:** Secure inquiry messages allowing finders and seekers to coordinate safe handoffs without exposing private phone numbers publicly.
* **Complete Recovery Lifecycle:** Explicit state machine tracking items from `Pending Verification` $\to$ `Verified Active` $\to$ `Potential Match` $\to$ `Contacted` $\to$ `Recovered`.
* **Administrative Moderation:** Dashboard tools for approving reports, rejecting submissions with mandatory logged explanations, soft-deleting obsolete listings, and reviewing audit trails.
* **Audit Logging:** System logs capturing administrative actions (`VERIFY_REPORT`, `REJECT_REPORT`, `DELETE_REPORT`) with timestamps and user references.
* **Dynamic Analytics Dashboard:** Five Chart.js visualizations driven by live database queries, complete with empty-state handling.

---

## Explainable Smart Matching

Unlike conventional portals that require manual searching through pages of listings, CampusFind includes an algorithmic matching engine that triggers whenever a report is submitted or updated.

### Matching Constraints
* **Opposite Type Comparison:** `lost` items are evaluated exclusively against active `found` reports, and vice versa.
* **Lifecycle Filtering:** Only active, non-deleted, opposite-type reports within the university system are evaluated.

### 5-Factor Scoring Weights
Matches are evaluated on a 100-point scale across five deterministic factors:

| Factor | Weight | Scoring Method |
|---|---|---|
| **Category Similarity** | **30%** | Exact match between categorized taxonomy tiers (e.g., *Electronics & Gadgets*). |
| **Item Name / Title** | **25%** | Evaluates token overlap, word containment, and character-level similarity (e.g., *"Casio fx-991EX"* $\leftrightarrow$ *"Casio Calculator"*). |
| **Description Keywords** | **20%** | Filters common campus filler words and measures shared distinctive descriptive keywords (*black*, *scratch*, *fountain*, *lanyard*). |
| **Location Proximity** | **15%** | Standardizes campus landmarks and building zones (*CSE Block*, *Library 2nd Floor*, *Cafeteria*, *Auditorium*). |
| **Date Proximity** | **10%** | Proximity curve rewarding reports filed on the same day ($10/10$) down to zero after three weeks. |

### Confidence Levels
* **Strong Potential Match:** Score $\ge 80\%$
* **Possible Match:** Score $60\% - 79\%$
* Scores below $60\%$ are filtered out to prevent alert fatigue.

---

## Explainable Matching Breakdown

CampusFind avoids "black-box" scores. Both students and administrators can inspect the exact breakdown of why two items were paired:

```text
Potential Match — 88% (Strong Potential Match)

[✓] Category Match (30/30)    : Exact Category Match (Books & Stationery)
[✓] Item Name (19/25)         : Highly similar item name (blue, parker, pen)
[✓] Description (15/20)       : Shared description keywords: blue, parker, fountain, pen
[✓] Location Proximity (14/15): Same campus building / block zone (LIBRARY)
[✓] Date Proximity (10/10)    : Reported on the exact same date
```

This transparency helps campus students quickly evaluate whether a found item belongs to them before initiating a contact request.

---

## Live Alert System

CampusFind features an integrated notifications engine that informs users about important milestones:

### Notification Triggers
1. **Administrative Verification:** Sent to the owner when an administrator approves a report.
2. **Administrative Rejection:** Sent to the owner if an administrator rejects a report, containing the exact reason entered by the moderator.
3. **Smart Match Detected:** Sent to owners of both the lost and found reports when a candidate pair scores $\ge 60\%$.
4. **Contact Request Received:** Sent to the item finder or reporter when another student sends an inquiry.
5. **Item Recovery:** Sent when an item is marked successfully recovered.

### Delivery Architecture
* Delivered via lightweight asynchronous AJAX polling (`api/notifications_poll.php`).
* Polls at non-intrusive 12-second intervals.
* Client-side toast deduplication tracks `seenNotificationIds` to prevent repeating toast alerts.
* Includes persistent unread counters in the navigation header and a dedicated notification history view.

---

## Recovery Lifecycle

Every item reported in CampusFind progresses through a strict, transparent lifecycle:

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

* **Authorization Guard:** Non-owners cannot mark an item as recovered.
* **Audit Trail:** Recoveries record both the exact timestamp (`recovered_at`) and the user ID (`recovered_by`).
* **Match Resolution:** Marking an item recovered automatically updates corresponding records in `potential_matches` to `resolved`.

---

## Admin Dashboard & Moderation

The administrative interface provides oversight for campus security staff:

* **Moderation Queue:** Filter reports by pending, verified, or rejected statuses.
* **Mandatory Rejection Reasons:** Admins cannot reject a report without providing an explanation (e.g., *"Photo is blurry"*, *"Lacks distinguishing details"*), which is sent directly to the student.
* **Audit Log (`admin_activity_log`):** Records every admin action with timestamps, admin identifier, and full change details.
* **Soft Deletion:** Maintains historical records and database integrity while hiding invalid listings from public search.

---

## Data-Driven Analytics

The administrative analytics section provides five distinct Chart.js visualizations driven directly by MySQL queries:

1. **Lost vs. Found Distribution (Doughnut Chart):** Compares the distribution between active lost listings, active found listings, and successfully recovered items.
2. **Report Activity Over Time (Line Chart):** Visualizes submission trends for lost and found reports across daily/monthly intervals.
3. **Items by Category (Horizontal Bar Chart):** Identifies the most frequently lost belongings (e.g., ID cards vs. electronics) to guide campus security focus.
4. **Recovery Performance (Comparative Bar Chart):** Compares total submissions against successful recoveries per interval.
5. **Report Status Lifecycle (Bar Chart):** Monitors the distribution of reports across pending, verified, recovered, and rejected states.

*All charts feature responsive sizing, unified color tokens, and graceful empty-state handling (`renderEmptyChart`).*

---

## Technology Stack

* **Frontend:**
  * HTML5 / CSS3 / JavaScript (Vanilla ES6+)
  * Bootstrap 5 (Responsive UI grid & components)
  * FontAwesome 6 (Campus icons)
  * Chart.js (Data visualizations)
* **Backend:**
  * PHP 8.x (Native procedural/modular architecture)
  * Session-based authentication with CSRF protection and role guards
* **Database:**
  * MySQL / MariaDB (InnoDB, `utf8mb4_unicode_ci`)
  * PDO (Prepared statements preventing SQL injection)
* **Server Environment:**
  * Apache (XAMPP) or PHP Built-in Server

---

## Project Structure

```text
CampusFind/
├── admin/                     # Administrator dashboard & moderation
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
│   ├── css/
│   │   └── style.css          # Core styles & utility classes
│   └── js/
│       ├── analytics.js       # Chart.js initialization & fallbacks
│       ├── live_poll.js       # Background alert polling & toasts
│       └── main.js            # General DOM & modal interactions
├── auth/                      # Authentication flows
│   ├── login.php              # Secure login with role redirect
│   ├── logout.php             # Session termination
│   └── register.php           # Student registration
├── config/                    # Core configuration & engines
│   ├── db.php                 # Active database connection (PDO)
│   ├── db.example.php         # Environment configuration template
│   ├── helpers.php            # Security & sanitization utilities
│   ├── matcher.php            # 5-factor smart matching engine
│   └── session.php            # Session initiation & state helpers
├── database/                  # Schema & migrations
│   └── campusfind.sql         # Production-ready database schema & seed
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
│   └── .gitkeep               # Directory structure preservation
├── index.php                  # Public item browse & multi-filter search
├── item_details.php           # Comprehensive item view & claim trigger
├── setup_database.php         # Self-contained database setup & seeder
├── test_e2e_advanced.php      # Comprehensive 31-point test suite
├── test_workflow.php          # Core workflow audit script
├── .env.example               # Environment variables template
├── .gitignore                 # Git ignore rules
└── README.md                  # Project documentation
```

---

## Installation & Setup Guide

### Prerequisites
* [XAMPP](https://www.apachefriends.org/) (with Apache, MySQL, and PHP 8.0+)
* Modern Web Browser (Chrome, Firefox, Edge, Safari)
* Git

### Step-by-Step Installation

1. **Clone the Repository:**
   ```bash
   git clone <YOUR_GITHUB_REPO_URL>
   cd fsd_lost_and_found
   ```

2. **Move to Web Directory:**
   Copy the project directory to your web server root:
   * **XAMPP Windows:** `C:\xampp\htdocs\fsd_lost_and_found`
   * **Linux / macOS:** `/var/www/html/fsd_lost_and_found`

3. **Start Apache & MySQL:**
   Open the XAMPP Control Panel and start both **Apache** and **MySQL**.

4. **Import Database:**
   * Open [phpMyAdmin](http://localhost/phpmyadmin) in your browser.
   * Create a new database named `fsd_lost_and_found`.
   * Click **Import** and select `database/campusfind.sql`.
   * Click **Import** to populate the tables and seed data.
   
   *Alternatively, run the automated setup script:*
   ```bash
   php setup_database.php
   ```

5. **Configure Database Connection:**
   Check `config/db.php` (default configuration connects to `127.0.0.1`, user `root`, no password). Adjust if your MySQL setup uses custom credentials.

6. **Access the Application:**
   Open your browser and navigate to:
   ```text
   http://localhost/fsd_lost_and_found
   ```
   *Or using PHP built-in server:*
   ```bash
   php -S localhost:8000
   ```
   Then visit `http://localhost:8000`.

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

## Screenshots

<!-- Add your screenshots in an assets/screenshots/ directory and link them below -->

### 1. Landing Page & Public Browse
*Multi-token search, category filters, and active campus listings.*  
![Landing Page](https://via.placeholder.com/800x450.png?text=CampusFind+Public+Browse+%26+Search)

### 2. Student Dashboard & Live Alerts
*Overview of student activity, quick reporting, and real-time alert notifications.*  
![Student Dashboard](https://via.placeholder.com/800x450.png?text=Student+Dashboard+%26+Live+Alerts)

### 3. Report Submission & Duplicate Guard
*Submission form with instant debounced duplicate detection warnings.*  
![Report Submission](https://via.placeholder.com/800x450.png?text=Report+Item+with+Duplicate+Detection)

### 4. Explainable Smart Matching
*Detailed 5-factor transparent similarity score breakdown.*  
![Smart Matching](https://via.placeholder.com/800x450.png?text=Explainable+Smart+Matching+Breakdown)

### 5. Admin Moderation & Activity Logs
*Report verification queue with mandatory rejection reasons and audit logging.*  
![Admin Moderation](https://via.placeholder.com/800x450.png?text=Admin+Moderation+%26+Activity+Logs)

### 6. Data-Driven Analytics
*Five Chart.js visualizations driven by real database queries.*  
![Analytics Dashboard](https://via.placeholder.com/800x450.png?text=Database-Driven+Analytics+Dashboard)

---

## What Makes CampusFind Different?

1. **Explainable Smart Matching:** Rather than presenting an arbitrary number, CampusFind breaks down exact reasons (shared keywords, exact category, location zone, date proximity) why two items likely match.
2. **Duplicate Detection:** Prevents clutter by proactively alerting students if an identical report was already submitted within their category or campus zone.
3. **Event-Based Alerts:** Real system events trigger targeted notifications—moderation reviews, potential matches, and handover requests are never missed.
4. **Complete Recovery Lifecycle:** Distinct tracking from initial report through moderation, matching, contact, and final handoff.
5. **Authentic Data-Driven Administration:** Dashboard counts and charts represent live database records, with automatic recalculations when items are approved, rejected, or recovered.

---

## License

This project was developed for academic and campus community purposes under the [MIT License](LICENSE).
