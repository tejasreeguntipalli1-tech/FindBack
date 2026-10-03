<?php
/**
 * Smart Matching Engine (Potential Match Detection)
 *
 * Fully algorithmic, explainable similarity scorer based on:
 * 1. Category (30%)
 * 2. Item Name / Title (25%)
 * 3. Description Keywords (20%)
 * 4. Location Proximity (15%)
 * 5. Date Proximity (10%)
 *
 * Implements Prompt Sections 4, 5, 6, 7, 9
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function get_stop_words() {
    return [
        'the', 'is', 'at', 'which', 'on', 'a', 'an', 'in', 'and', 'or', 'for', 'to', 'with',
        'my', 'it', 'was', 'i', 'of', 'this', 'that', 'there', 'left', 'found', 'lost', 'by',
        'from', 'as', 'he', 'she', 'they', 'we', 'you', 'me', 'him', 'her', 'them', 'be', 'are',
        'been', 'have', 'has', 'had', 'do', 'does', 'did', 'but', 'if', 'so', 'out', 'up', 'down',
        'some', 'any', 'near', 'under', 'over', 'into', 'onto', 'about', 'just', 'please', 'help',
        'item', 'belonging', 'misplaced', 'around', 'desk', 'table'
    ];
}

function extract_keywords($text) {
    $clean = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text ?? ''));
    $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
    $stopWords = array_flip(get_stop_words());

    $filtered = [];
    foreach ($words as $word) {
        if (strlen($word) >= 2 && !isset($stopWords[$word])) {
            $filtered[] = $word;
        }
    }
    return array_unique($filtered);
}

/**
 * Intelligent Campus Location Comparison
 * Evaluates core building anchors, room numbers, and proximity keywords.
 */
function calculate_location_similarity($loc1, $loc2) {
    $l1 = strtolower(trim($loc1 ?? ''));
    $l2 = strtolower(trim($loc2 ?? ''));

    if (empty($l1) || empty($l2)) {
        return [
            'score'  => 0,
            'max'    => 15,
            'status' => 'low',
            'symbol' => '✗',
            'label'  => 'Location unspecified'
        ];
    }

    // Exact string match
    if ($l1 === $l2) {
        return [
            'score'  => 15,
            'max'    => 15,
            'status' => 'exact',
            'symbol' => '✓',
            'label'  => 'Exact same campus location'
        ];
    }

    // Known campus building / department anchors
    $anchors = [
        'cse', 'ece', 'mech', 'mechanical', 'it', 'civil', 'library', 'cafeteria', 'canteen',
        'sports', 'hostel', 'auditorium', 'seminar', 'admin', 'gate', 'parking', 'lab', 'laboratory'
    ];

    $tokens1 = extract_keywords($l1);
    $tokens2 = extract_keywords($l2);

    $sharedTokens = array_intersect($tokens1, $tokens2);

    // Extract numbers (e.g. room 204, hall 2)
    preg_match_all('/\d+/', $l1, $nums1);
    preg_match_all('/\d+/', $l2, $nums2);
    $sharedNums = array_intersect($nums1[0] ?? [], $nums2[0] ?? []);

    $sharedAnchors = array_intersect($tokens1, $tokens2, $anchors);

    if (!empty($sharedAnchors)) {
        // Shared primary campus building / facility
        if (!empty($sharedNums)) {
            // Same room number in same building!
            $score = 15;
            $label = 'Same room & building zone (' . strtoupper(implode(', ', $sharedAnchors)) . ' ' . implode(', ', $sharedNums) . ')';
        } elseif (strpos($l1, $l2) !== false || strpos($l2, $l1) !== false) {
            $score = 14;
            $label = 'Same campus building / block zone (' . strtoupper(implode(', ', $sharedAnchors)) . ')';
        } else {
            $score = 12;
            $label = 'Matching campus building (' . strtoupper(implode(', ', $sharedAnchors)) . ')';
        }
        return [
            'score'  => $score,
            'max'    => 15,
            'status' => 'exact',
            'symbol' => '✓',
            'label'  => $label
        ];
    }

    // General token overlap
    if (!empty($sharedTokens)) {
        $overlapRatio = count($sharedTokens) / max(1, count(array_unique(array_merge($tokens1, $tokens2))));
        $score = min(11, max(7, (int)round($overlapRatio * 15)));
        return [
            'score'  => $score,
            'max'    => 15,
            'status' => 'moderate',
            'symbol' => '~',
            'label'  => 'Nearby campus landmark (' . implode(', ', array_slice($sharedTokens, 0, 3)) . ')'
        ];
    }

    // Substring containment check
    if (strpos($l1, $l2) !== false || strpos($l2, $l1) !== false) {
        return [
            'score'  => 10,
            'max'    => 15,
            'status' => 'moderate',
            'symbol' => '~',
            'label'  => 'Overlapping location area'
        ];
    }

    return [
        'score'  => 0,
        'max'    => 15,
        'status' => 'low',
        'symbol' => '✗',
        'label'  => 'Different campus locations'
    ];
}

/**
 * Intelligent Item Name Similarity
 * Combines token overlap, brand/model containment, and character similarity.
 */
function calculate_name_similarity($title1, $title2) {
    $t1 = strtolower(trim($title1 ?? ''));
    $t2 = strtolower(trim($title2 ?? ''));

    if (empty($t1) || empty($t2)) {
        return [
            'score'  => 0,
            'max'    => 25,
            'status' => 'low',
            'symbol' => '✗',
            'label'  => 'Item name unspecified'
        ];
    }

    if ($t1 === $t2) {
        return [
            'score'  => 25,
            'max'    => 25,
            'status' => 'exact',
            'symbol' => '✓',
            'label'  => 'Identical item name'
        ];
    }

    $tokens1 = extract_keywords($t1);
    $tokens2 = extract_keywords($t2);

    $intersect = array_intersect($tokens1, $tokens2);
    $union = array_unique(array_merge($tokens1, $tokens2));
    $jaccard = count($union) > 0 ? (count($intersect) / count($union)) : 0;

    // Token containment: e.g. "Casio Calculator" inside "Black Casio Scientific Calculator"
    $containment = 0;
    if (count($tokens1) > 0 && count($tokens2) > 0) {
        $smaller = min(count($tokens1), count($tokens2));
        $containment = count($intersect) / $smaller;
    }

    similar_text($t1, $t2, $simPercent);
    $textSim = $simPercent / 100.0;

    // Combined metric: 40% Jaccard + 35% Token Containment + 25% String Similarity
    $combined = ($jaccard * 0.40) + ($containment * 0.35) + ($textSim * 0.25);
    $nameScore = min(25, max(0, (int)round($combined * 25)));

    $status = 'low';
    $symbol = '✗';
    $label = 'Different item names';

    if ($nameScore >= 18) {
        $status = 'exact';
        $symbol = '✓';
        $label = 'Highly similar item name (' . implode(', ', array_slice($intersect, 0, 3)) . ')';
    } elseif ($nameScore >= 10) {
        $status = 'moderate';
        $symbol = '~';
        $label = 'Moderate keyword overlap in title';
    }

    return [
        'score'  => $nameScore,
        'max'    => 25,
        'status' => $status,
        'symbol' => $symbol,
        'label'  => $label
    ];
}

/**
 * Meaningful Description Keyword Similarity
 */
function calculate_description_similarity($desc1, $desc2) {
    $tokens1 = extract_keywords($desc1 ?? '');
    $tokens2 = extract_keywords($desc2 ?? '');

    if (empty($tokens1) || empty($tokens2)) {
        return [
            'score'  => 6,
            'max'    => 20,
            'status' => 'low',
            'symbol' => '~',
            'label'  => 'Standard description context'
        ];
    }

    $intersect = array_intersect($tokens1, $tokens2);
    $denom = sqrt(count($tokens1) * count($tokens2));
    $overlapRatio = $denom > 0 ? (count($intersect) / $denom) : 0;

    $score = min(20, max(0, (int)round($overlapRatio * 25))); // boosted for meaningful words
    $sharedWords = array_slice($intersect, 0, 4);

    if ($score >= 12) {
        $status = 'exact';
        $symbol = '✓';
        $label = 'Shared description keywords: ' . implode(', ', $sharedWords);
    } elseif ($score >= 6) {
        $status = 'moderate';
        $symbol = '~';
        $label = !empty($sharedWords) ? 'Matching terms: ' . implode(', ', $sharedWords) : 'Contextually similar description';
    } else {
        $status = 'low';
        $symbol = '~';
        $label = 'General description context';
    }

    return [
        'score'  => $score,
        'max'    => 20,
        'status' => $status,
        'symbol' => $symbol,
        'label'  => $label
    ];
}

/**
 * Transparent 5-Factor Similarity Calculator
 */
function calculate_smart_match($lostReport, $foundReport, $categoryName = '') {
    $factors = [];

    // 1. Category Match (30 points)
    if ($lostReport['category_id'] == $foundReport['category_id']) {
        $factors['category'] = [
            'score'  => 30,
            'max'    => 30,
            'status' => 'exact',
            'symbol' => '✓',
            'label'  => 'Exact Category Match (' . ($categoryName ?: 'Matched Category') . ')'
        ];
    } else {
        $factors['category'] = [
            'score'  => 0,
            'max'    => 30,
            'status' => 'mismatch',
            'symbol' => '✗',
            'label'  => 'Different categories'
        ];
    }

    // 2. Item Name / Title Similarity (25 points)
    $factors['name'] = calculate_name_similarity($lostReport['title'], $foundReport['title']);

    // 3. Description Keywords (20 points)
    $factors['description'] = calculate_description_similarity($lostReport['description'], $foundReport['description']);

    // 4. Campus Location Match (15 points)
    $factors['location'] = calculate_location_similarity($lostReport['location'], $foundReport['location']);

    // 5. Date Proximity (10 points)
    $date1 = strtotime($lostReport['item_date'] ?? 'now');
    $date2 = strtotime($foundReport['item_date'] ?? 'now');
    $diffDays = abs($date1 - $date2) / 86400;

    if ($diffDays <= 0.5) {
        $dateScore = 10;
        $dateLabel = 'Reported on the exact same date';
    } elseif ($diffDays <= 2) {
        $dateScore = 9;
        $dateLabel = 'Within 2 days of each other';
    } elseif ($diffDays <= 5) {
        $dateScore = 7;
        $dateLabel = 'Within 5 days of each other';
    } elseif ($diffDays <= 10) {
        $dateScore = 5;
        $dateLabel = 'Within 10 days';
    } elseif ($diffDays <= 20) {
        $dateScore = 3;
        $dateLabel = 'Within 3 weeks';
    } else {
        $dateScore = 0;
        $dateLabel = 'More than 3 weeks apart';
    }

    $factors['date'] = [
        'score'  => $dateScore,
        'max'    => 10,
        'status' => $dateScore >= 7 ? 'exact' : ($dateScore >= 4 ? 'moderate' : 'low'),
        'symbol' => $dateScore >= 7 ? '✓' : ($dateScore >= 4 ? '~' : '✗'),
        'label'  => $dateLabel
    ];

    $totalScore = $factors['category']['score']
                + $factors['name']['score']
                + $factors['description']['score']
                + $factors['location']['score']
                + $factors['date']['score'];

    $finalScore = min(100, max(0, $totalScore));

    // Section 6: Meaningful Confidence Levels
    if ($finalScore >= 80) {
        $confidenceLevel = 'strong';
        $confidenceLabel = 'Strong Potential Match';
    } elseif ($finalScore >= 60) {
        $confidenceLevel = 'moderate';
        $confidenceLabel = 'Possible Match';
    } else {
        $confidenceLevel = 'low';
        $confidenceLabel = 'Low Confidence Match';
    }

    return [
        'score'            => $finalScore,
        'confidence_level' => $confidenceLevel,
        'confidence_label' => $confidenceLabel,
        'factors'          => $factors
    ];
}

/**
 * Scan for matches for a given report and save candidates into `potential_matches`.
 * Prevents duplicate alerts (Section 9).
 */
function run_matching_engine_for_report($pdo, $reportId) {
    // 1. Fetch target report
    $stmt = $pdo->prepare("SELECT r.*, c.name as category_name FROM reports r JOIN categories c ON r.category_id = c.id WHERE r.id = ? AND r.is_deleted = 0");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();

    if (!$report || $report['status'] === 'recovered') {
        return 0;
    }

    // Opposite type: lost compares to found, found compares to lost (Section 4)
    $oppositeType = ($report['type'] === 'lost') ? 'found' : 'lost';

    // Fetch active candidates
    $candStmt = $pdo->prepare("
        SELECT r.*, c.name as category_name 
        FROM reports r 
        JOIN categories c ON r.category_id = c.id 
        WHERE r.type = ? 
          AND r.status = 'active' 
          AND r.is_deleted = 0 
          AND r.id != ?
    ");
    $candStmt->execute([$oppositeType, $reportId]);
    $candidates = $candStmt->fetchAll();

    $newMatchesCount = 0;

    foreach ($candidates as $candidate) {
        $lost = ($report['type'] === 'lost') ? $report : $candidate;
        $found = ($report['type'] === 'found') ? $report : $candidate;

        $catName = ($lost['category_id'] == $found['category_id']) ? $lost['category_name'] : '';
        $matchResult = calculate_smart_match($lost, $found, $catName);

        // Section 6: Candidate threshold (only store plausible matches)
        if ($matchResult['score'] >= 50) {
            $factorsJson = json_encode($matchResult['factors']);

            // Check if already in potential_matches
            $chkStmt = $pdo->prepare("SELECT id, notification_sent, status FROM potential_matches WHERE lost_report_id = ? AND found_report_id = ?");
            $chkStmt->execute([$lost['id'], $found['id']]);
            $existing = $chkStmt->fetch();

            $matchId = null;
            $shouldNotify = false;

            if ($existing) {
                $matchId = $existing['id'];
                // Update score and factors
                $upStmt = $pdo->prepare("UPDATE potential_matches SET match_score = ?, factors_json = ? WHERE id = ?");
                $upStmt->execute([$matchResult['score'], $factorsJson, $matchId]);
                
                // Only notify if notification was not yet sent and it's a strong match (Section 6 & 9)
                if ((int)$existing['notification_sent'] === 0 && $matchResult['score'] >= 65 && $existing['status'] !== 'dismissed') {
                    $shouldNotify = true;
                }
            } else {
                // Insert new match
                $insStmt = $pdo->prepare("
                    INSERT INTO potential_matches (lost_report_id, found_report_id, match_score, factors_json, status, notification_sent, created_at)
                    VALUES (?, ?, ?, ?, 'pending', 0, NOW())
                ");
                $insStmt->execute([$lost['id'], $found['id'], $matchResult['score'], $factorsJson]);
                $matchId = $pdo->lastInsertId();
                $newMatchesCount++;

                // Notify for strong or moderate matches (>= 60%)
                if ($matchResult['score'] >= 60) {
                    $shouldNotify = true;
                }
            }

            // Section 9: Prevent duplicate alerts
            if ($shouldNotify && $matchId) {
                $lostOwnerId = $lost['user_id'];
                $foundOwnerId = $found['user_id'];
                $baseUrl = get_base_url();

                create_notification(
                    $pdo,
                    $lostOwnerId,
                    "Smart Match ({$matchResult['score']}%)",
                    "A potential match for your lost item '{$lost['title']}' was detected with found report '{$found['title']}'.",
                    'match',
                    $baseUrl . "/student/matches.php?report_id=" . $lost['id'],
                    $found['id']
                );

                if ($foundOwnerId != $lostOwnerId) {
                    create_notification(
                        $pdo,
                        $foundOwnerId,
                        "Smart Match ({$matchResult['score']}%)",
                        "Your found item '{$found['title']}' may match a lost item reported on campus.",
                        'match',
                        $baseUrl . "/student/matches.php?report_id=" . $found['id'],
                        $lost['id']
                    );
                }

                $pdo->prepare("UPDATE potential_matches SET notification_sent = 1 WHERE id = ?")->execute([$matchId]);
            }
        }
    }

    return $newMatchesCount;
}
