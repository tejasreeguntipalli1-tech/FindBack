<?php
/**
 * Database Setup & Seed Script
 * Full Stack Development (FSD) Project: Campus Lost & Found Platform
 */

$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    // 1. Connect without database to create database if not exists
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $dbName = 'fsd_lost_and_found';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `$dbName`;");

    echo "Database `$dbName` created/selected successfully.<br>\n";

    // 2. Drop existing tables if re-running for fresh seed
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DROP TABLE IF EXISTS admin_activity_log;");
    $pdo->exec("DROP TABLE IF EXISTS notifications;");
    $pdo->exec("DROP TABLE IF EXISTS contact_requests;");
    $pdo->exec("DROP TABLE IF EXISTS potential_matches;");
    $pdo->exec("DROP TABLE IF EXISTS reports;");
    $pdo->exec("DROP TABLE IF EXISTS categories;");
    $pdo->exec("DROP TABLE IF EXISTS users;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 3. Create Users Table
    $pdo->exec("
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('student', 'admin') DEFAULT 'student',
            student_id VARCHAR(50) DEFAULT NULL,
            department VARCHAR(100) DEFAULT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            avatar VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    // 4. Create Categories Table
    $pdo->exec("
        CREATE TABLE categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(80) NOT NULL UNIQUE,
            icon VARCHAR(50) DEFAULT 'fa-box',
            description VARCHAR(255) DEFAULT ''
        ) ENGINE=InnoDB;
    ");

    // 5. Create Reports Table
    $pdo->exec("
        CREATE TABLE reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type ENUM('lost', 'found') NOT NULL,
            category_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            description TEXT NOT NULL,
            location VARCHAR(150) NOT NULL,
            item_date DATE NOT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            status ENUM('active', 'recovered', 'closed') DEFAULT 'active',
            verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
            rejection_reason TEXT DEFAULT NULL,
            is_deleted TINYINT(1) DEFAULT 0,
            recovered_at DATETIME DEFAULT NULL,
            recovered_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB;
    ");

    // 6. Create Potential Matches Table
    $pdo->exec("
        CREATE TABLE potential_matches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lost_report_id INT NOT NULL,
            found_report_id INT NOT NULL,
            match_score INT NOT NULL,
            factors_json TEXT NOT NULL,
            status ENUM('pending', 'contacted', 'resolved', 'dismissed') DEFAULT 'pending',
            notification_sent TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (lost_report_id) REFERENCES reports(id) ON DELETE CASCADE,
            FOREIGN KEY (found_report_id) REFERENCES reports(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    // 7. Create Contact Requests / Claims Table
    $pdo->exec("
        CREATE TABLE contact_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            receiver_id INT NOT NULL,
            report_id INT NOT NULL,
            message TEXT NOT NULL,
            contact_email VARCHAR(150) NOT NULL,
            contact_phone VARCHAR(50) DEFAULT NULL,
            status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    // 8. Create Notifications Table
    $pdo->exec("
        CREATE TABLE notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('match', 'verification', 'recovery', 'contact', 'system') DEFAULT 'system',
            link VARCHAR(255) DEFAULT NULL,
            related_report_id INT DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    // 9. Create Admin Activity Log Table
    $pdo->exec("
        CREATE TABLE admin_activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            report_id INT DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    echo "Tables created successfully.<br>\n";

    // 10. Seed Users
    $passwordAdmin = password_hash('Admin@123', PASSWORD_BCRYPT);
    $passwordStudent = password_hash('Student@123', PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, student_id, department, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // Admin
    $stmt->execute(['Campus Administrator', 'admin@campus.edu', $passwordAdmin, 'admin', 'ADM-001', 'Campus Security & Welfare', '+91 98765 43210']);
    $adminId = $pdo->lastInsertId();

    // Students
    $stmt->execute(['Alex Johnson', 'alex@campus.edu', $passwordStudent, 'student', 'CS2024-042', 'Computer Science & Engineering', '+91 91234 56789']);
    $alexId = $pdo->lastInsertId();

    $stmt->execute(['Sarah Smith', 'sarah@campus.edu', $passwordStudent, 'student', 'EC2023-118', 'Electronics & Communication', '+91 98111 22334']);
    $sarahId = $pdo->lastInsertId();

    $stmt->execute(['Rahul Sharma', 'rahul@campus.edu', $passwordStudent, 'student', 'ME2024-089', 'Mechanical Engineering', '+91 97222 33445']);
    $rahulId = $pdo->lastInsertId();

    $stmt->execute(['Priya Patel', 'priya@campus.edu', $passwordStudent, 'student', 'IT2024-015', 'Information Technology', '+91 96333 44556']);
    $priyaId = $pdo->lastInsertId();

    // 11. Seed Categories
    $categories = [
        ['ID Cards & Badges', 'fa-id-card', 'Student IDs, access cards, library badges'],
        ['Mobile Phones', 'fa-mobile-screen', 'Smartphones, feature phones, cases'],
        ['Wallets & Purses', 'fa-wallet', 'Wallets, coin purses, money clips'],
        ['Keys & Keychains', 'fa-key', 'Hostel keys, vehicle keys, locker keys'],
        ['Electronics & Gadgets', 'fa-laptop', 'Laptops, calculators, chargers, earphones, smart watches'],
        ['Books & Stationery', 'fa-book', 'Textbooks, notebooks, scientific calculators, stationery pouches'],
        ['Bags & Backpacks', 'fa-bag-shopping', 'Backpacks, laptop sleeves, gym bags'],
        ['Accessories & Wearables', 'fa-glasses', 'Glasses, wristwatches, jewelry, water bottles'],
        ['Other Belongings', 'fa-box-open', 'Umbrellas, sports gear, misc items']
    ];

    $catStmt = $pdo->prepare("INSERT INTO categories (name, icon, description) VALUES (?, ?, ?)");
    $catMap = [];
    foreach ($categories as $cat) {
        $catStmt->execute([$cat[0], $cat[1], $cat[2]]);
        $catMap[$cat[0]] = $pdo->lastInsertId();
    }

    // 12. Seed Reports (Historical & Active for realistic graphs and metrics)
    // We will insert 14 diverse reports with varying statuses and dates spanning Aug, Sep, Oct 2026.
    $repStmt = $pdo->prepare("
        INSERT INTO reports (user_id, type, category_id, title, description, location, item_date, status, verification_status, rejection_reason, recovered_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Report 1: Alex Lost Black Casio Calculator (Active, Verified) - Oct 02, 2026
    $repStmt->execute([
        $alexId, 'lost', $catMap['Electronics & Gadgets'],
        'Black Casio fx-991EX Scientific Calculator',
        'Left my black scientific calculator on the second bench in CSE Block room 204 after the morning math lecture. It has a small scratch on the back cover.',
        'CSE Block Room 204', '2026-10-02', 'active', 'verified', null, null, '2026-10-02 11:30:00'
    ]);
    $rep1Id = $pdo->lastInsertId();

    // Report 2: Sarah Found Black Scientific Calculator (Active, Verified) - Oct 02, 2026 -> WILL MATCH WITH REPORT 1!
    $repStmt->execute([
        $sarahId, 'found', $catMap['Electronics & Gadgets'],
        'Black Casio Calculator',
        'Found a Casio calculator lying on desk row 2 in CSE Block lecture hall 204 around 1:00 PM. Handed it to the security desk on the ground floor.',
        'CSE Block', '2026-10-02', 'active', 'verified', null, null, '2026-10-02 13:45:00'
    ]);
    $rep2Id = $pdo->lastInsertId();

    // Report 3: Rahul Lost Brown Leather Wallet (Active, Pending) - Oct 01, 2026
    $repStmt->execute([
        $rahulId, 'lost', $catMap['Wallets & Purses'],
        'Brown Leather Tommy Hilfiger Wallet',
        'Lost a dark brown leather bi-fold wallet possibly near the Central Cafeteria or sports complex benches. Contains my student ID and driver license.',
        'Central Cafeteria', '2026-10-01', 'active', 'pending', null, null, '2026-10-01 16:15:00'
    ]);
    $rep3Id = $pdo->lastInsertId();

    // Report 4: Priya Found Set of Keys with Blue Lanyard (Active, Verified) - Sep 28, 2026
    $repStmt->execute([
        $priyaId, 'found', $catMap['Keys & Keychains'],
        'Hostel Room Keys on Blue Campus Lanyard',
        'Found three brass keys with a blue University lanyard on the pathway between Library and Academic Block B.',
        'Library Pathway', '2026-09-28', 'active', 'verified', null, null, '2026-09-28 09:20:00'
    ]);
    $rep4Id = $pdo->lastInsertId();

    // Report 5: Alex Found Black Wireless Earbuds (Active, Pending) - Sep 25, 2026
    $repStmt->execute([
        $alexId, 'found', $catMap['Electronics & Gadgets'],
        'OnePlus Nord Buds Black Case',
        'Found an oval matte black charging case with earbuds inside near the study table at Central Library 1st Floor.',
        'Central Library 1st Floor', '2026-09-25', 'active', 'pending', null, null, '2026-09-25 15:10:00'
    ]);
    $rep5Id = $pdo->lastInsertId();

    // Report 6: Sarah Lost College Student ID Card (Recovered!) - Sep 20, 2026
    $repStmt->execute([
        $sarahId, 'lost', $catMap['ID Cards & Badges'],
        'Student ID Card - Sarah Smith',
        'Dropped my ID card while rushing to the ECE seminar hall.',
        'ECE Seminar Hall', '2026-09-20', 'recovered', 'verified', null, '2026-09-22 14:00:00', '2026-09-20 10:00:00'
    ]);
    $rep6Id = $pdo->lastInsertId();

    // Report 7: Rahul Found Student ID Card (Recovered!) - Sep 21, 2026
    $repStmt->execute([
        $rahulId, 'found', $catMap['ID Cards & Badges'],
        'Found Student ID Card Sarah Smith',
        'Found Sarah Smith ID card near ECE hall entrance and returned it via Security Desk.',
        'ECE Hall Entrance', '2026-09-21', 'recovered', 'verified', null, '2026-09-22 14:00:00', '2026-09-21 11:30:00'
    ]);
    $rep7Id = $pdo->lastInsertId();

    // Report 8: Priya Lost Blue Dell Laptop Backpack (Recovered!) - Sep 15, 2026
    $repStmt->execute([
        $priyaId, 'lost', $catMap['Bags & Backpacks'],
        'Navy Blue Dell Laptop Backpack',
        'Forgot backpack in the IT Computer Lab 3 under table 14. Contained notebooks and charger.',
        'IT Computer Lab 3', '2026-09-15', 'recovered', 'verified', null, '2026-09-16 11:00:00', '2026-09-15 17:00:00'
    ]);
    $rep8Id = $pdo->lastInsertId();

    // Report 9: Rahul Lost Data Structures Textbook (Active, Verified) - Sep 12, 2026
    $repStmt->execute([
        $rahulId, 'lost', $catMap['Books & Stationery'],
        'Data Structures & Algorithms in Java Textbook',
        'Hardcover textbook with yellow sticky notes on pages. Left in Room 102 Mechanical Block.',
        'Mechanical Block Room 102', '2026-09-12', 'active', 'verified', null, null, '2026-09-12 14:30:00'
    ]);
    $rep9Id = $pdo->lastInsertId();

    // Report 10: Alex Lost Ray-Ban Aviator Sunglasses (Active, Verified) - Sep 05, 2026
    $repStmt->execute([
        $alexId, 'lost', $catMap['Accessories & Wearables'],
        'Silver Metal Frame Sunglasses',
        'Lost prescription sunglasses in leather case near the outdoor basketball court.',
        'Sports Complex Court 2', '2026-09-05', 'active', 'verified', null, null, '2026-09-05 18:00:00'
    ]);
    $rep10Id = $pdo->lastInsertId();

    // Report 11: Sarah Found Milton Steel Water Bottle (Active, Verified) - Aug 28, 2026
    $repStmt->execute([
        $sarahId, 'found', $catMap['Accessories & Wearables'],
        'Silver Insulated Milton Water Bottle',
        'Left on the bench near the open auditorium during the evening club meeting.',
        'Open Air Auditorium', '2026-08-28', 'active', 'verified', null, null, '2026-08-28 19:15:00'
    ]);
    $rep11Id = $pdo->lastInsertId();

    // Report 12: Rahul Reported Fake/Spam item (Rejected by Admin) - Aug 20, 2026
    $repStmt->execute([
        $rahulId, 'found', $catMap['Other Belongings'],
        'Random flying UFO found',
        'Test submission to check system response.',
        'Campus Sky', '2026-08-20', 'closed', 'rejected', 'Invalid report content / test submission without real item details.', null, '2026-08-20 12:00:00'
    ]);
    $rep12Id = $pdo->lastInsertId();

    // Report 13: Priya Lost iPhone 13 Starlight (Active, Verified) - Oct 03, 2026
    $repStmt->execute([
        $priyaId, 'lost', $catMap['Mobile Phones'],
        'Apple iPhone 13 White/Starlight with Floral Case',
        'Slipped out of my jacket pocket in the campus cafe. Phone is locked with PIN.',
        'Central Cafeteria', '2026-10-03', 'active', 'verified', null, null, '2026-10-03 08:30:00'
    ]);
    $rep13Id = $pdo->lastInsertId();

    // Report 14: Alex Found iPhone 13 (Active, Pending) - Oct 03, 2026 -> WILL MATCH WITH REPORT 13!
    $repStmt->execute([
        $alexId, 'found', $catMap['Mobile Phones'],
        'White Apple iPhone with clear patterned cover',
        'Found on a sofa table in the cafeteria around 9:00 AM. Handed to cafe cashier.',
        'Central Cafeteria', '2026-10-03', 'active', 'pending', null, null, '2026-10-03 09:15:00'
    ]);
    $rep14Id = $pdo->lastInsertId();

    // 13. Seed Smart Potential Matches (Calculated mathematically using the 5 factors)
    // Match 1: Report 1 (Alex Lost Calculator) vs Report 2 (Sarah Found Calculator)
    // Category: 30% (Match)
    // Title/Name: 20% (High token overlap)
    // Description: 14% (Casio, calculator, CSE Block, 204)
    // Location: 13% (CSE Block Room 204 vs CSE Block)
    // Date: 10% (Same day: 2026-10-02)
    // Total = 87%
    $factors1 = json_encode([
        'category' => ['score' => 30, 'max' => 30, 'status' => 'exact', 'label' => 'Exact Category Match (Electronics & Gadgets)'],
        'name' => ['score' => 20, 'max' => 25, 'status' => 'high', 'label' => 'Similar Item Name ("Black Casio fx-991EX" vs "Black Casio")'],
        'description' => ['score' => 14, 'max' => 20, 'status' => 'moderate', 'label' => 'Shared keywords: Casio, calculator, CSE Block, 204'],
        'location' => ['score' => 13, 'max' => 15, 'status' => 'high', 'label' => 'Matching Location: CSE Block Room 204'],
        'date' => ['score' => 10, 'max' => 10, 'status' => 'exact', 'label' => 'Same Date: Oct 02, 2026']
    ]);

    $pdo->prepare("
        INSERT INTO potential_matches (lost_report_id, found_report_id, match_score, factors_json, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', '2026-10-02 13:46:00')
    ")->execute([$rep1Id, $rep2Id, 87, $factors1]);

    // Match 2: Report 13 (Priya Lost iPhone) vs Report 14 (Alex Found iPhone)
    // Total = 84%
    $factors2 = json_encode([
        'category' => ['score' => 30, 'max' => 30, 'status' => 'exact', 'label' => 'Exact Category Match (Mobile Phones)'],
        'name' => ['score' => 19, 'max' => 25, 'status' => 'high', 'label' => 'High Name Similarity: Apple iPhone 13'],
        'description' => ['score' => 12, 'max' => 20, 'status' => 'moderate', 'label' => 'Shared context: Cafeteria, White/Starlight, patterned cover'],
        'location' => ['score' => 14, 'max' => 15, 'status' => 'exact', 'label' => 'Identical Location: Central Cafeteria'],
        'date' => ['score' => 10, 'max' => 10, 'status' => 'exact', 'label' => 'Same Date: Oct 03, 2026']
    ]);

    $pdo->prepare("
        INSERT INTO potential_matches (lost_report_id, found_report_id, match_score, factors_json, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', '2026-10-03 09:16:00')
    ")->execute([$rep13Id, $rep14Id, 85, $factors2]);

    // 14. Seed Notifications
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // Notification for Alex (Calculator match)
    $notifStmt->execute([
        $alexId,
        'Smart Match Found (87%)',
        'A found report "#2 Black Casio Calculator" closely matches your lost report "#1 Black Casio fx-991EX".',
        'match',
        'student/matches.php?report_id=' . $rep1Id,
        0,
        '2026-10-02 13:46:00'
    ]);

    // Notification for Priya (iPhone match)
    $notifStmt->execute([
        $priyaId,
        'Smart Match Found (85%)',
        'A found item matching your iPhone 13 was reported at Central Cafeteria.',
        'match',
        'student/matches.php?report_id=' . $rep13Id,
        0,
        '2026-10-03 09:16:00'
    ]);

    // Notification for Sarah (ID Card verified)
    $notifStmt->execute([
        $sarahId,
        'Report Verified by Security Admin',
        'Your report for "Student ID Card - Sarah Smith" was verified and published.',
        'verification',
        'item_details.php?id=' . $rep6Id,
        1,
        '2026-09-20 10:15:00'
    ]);

    // Notification for Rahul (Rejection explanation)
    $notifStmt->execute([
        $rahulId,
        'Report Action: Rejected',
        'Your submission was rejected: Invalid report content / test submission without real item details.',
        'verification',
        'student/my_reports.php',
        1,
        '2026-08-20 12:05:00'
    ]);

    // 15. Seed Admin Activity Log
    $logStmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, report_id, action, details, created_at) VALUES (?, ?, ?, ?, ?)");
    $logStmt->execute([$adminId, $rep1Id, 'VERIFY_REPORT', 'Verified report #1 "Black Casio fx-991EX Scientific Calculator" submitted by Alex Johnson.', '2026-10-02 12:00:00']);
    $logStmt->execute([$adminId, $rep2Id, 'VERIFY_REPORT', 'Verified report #2 "Black Casio Calculator" submitted by Sarah Smith.', '2026-10-02 14:00:00']);
    $logStmt->execute([$adminId, $rep6Id, 'VERIFY_REPORT', 'Verified report #6 "Student ID Card - Sarah Smith".', '2026-09-20 10:15:00']);
    $logStmt->execute([$adminId, $rep6Id, 'MARK_RECOVERED', 'Marked report #6 as RECOVERED upon student pickup confirmation at Security Desk.', '2026-09-22 14:00:00']);
    $logStmt->execute([$adminId, $rep12Id, 'REJECT_REPORT', 'Rejected report #12: Invalid report content / test submission.', '2026-08-20 12:05:00']);
    $logStmt->execute([$adminId, $rep13Id, 'VERIFY_REPORT', 'Verified report #13 "Apple iPhone 13 White/Starlight" submitted by Priya Patel.', '2026-10-03 08:45:00']);

    // 16. Seed a Contact Request
    $conStmt = $pdo->prepare("INSERT INTO contact_requests (sender_id, receiver_id, report_id, message, contact_email, contact_phone, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $conStmt->execute([
        $alexId, $sarahId, $rep2Id,
        'Hi Sarah, I saw your found post for the Casio calculator in CSE Block room 204. That is mine! Can we meet at the security desk to verify?',
        'alex@campus.edu', '+91 91234 56789', 'pending', '2026-10-02 14:10:00'
    ]);

    echo "Initial seed data inserted successfully!<br>\n";
    echo "<b>Default Credentials:</b><br>\n";
    echo "Admin: <code>admin@campus.edu</code> / <code>Admin@123</code><br>\n";
    echo "Students: <code>alex@campus.edu</code>, <code>sarah@campus.edu</code>, <code>rahul@campus.edu</code>, <code>priya@campus.edu</code> / <code>Student@123</code><br>\n";

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
