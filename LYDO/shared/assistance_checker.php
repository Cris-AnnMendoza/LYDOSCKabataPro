<?php
/**
 * Smart Assistance Request Checker
 * - Auto Document Checker: missing files, wrong types, duplicates
 * - Proposal Reviewer: completeness scoring, missing sections
 * No external AI — pure PHP rule-based analysis.
 */

// ── Required document types ───────────────────────────────
const REQUIRED_DOCS = [
    'request_letter'   => 'Request Letter',
    'project_proposal' => 'Project Proposal',
    'participant_list' => 'Participant List',
    'sk_endorsement'   => 'SK Endorsement',
];

const ALLOWED_EXTENSIONS = ['pdf','doc','docx','jpg','jpeg','png'];
const MAX_FILE_SIZE_MB    = 10;

// ── Proposal completeness keywords ───────────────────────
const PROPOSAL_SECTIONS = [
    'objectives'       => ['objective','goal','aim','purpose','target'],
    'expected_outcome' => ['outcome','result','benefit','impact','achieve','output'],
    'description'      => ['activity','program','project','event','conduct','implement','organize'],
    'target_venue'     => ['venue','location','place','hall','center','barangay'],
    'participants'     => ['participant','youth','member','attendee','beneficiar'],
    'budget_requested' => [],  // numeric check
    'target_date'      => [],  // date check
];

/**
 * Check uploaded files for issues.
 * Returns array of issues and warnings.
 */
function checkDocuments(array $files, PDO $pdo, int $userId): array {
    $issues   = [];
    $warnings = [];
    $uploaded = [];

    foreach (REQUIRED_DOCS as $type => $label) {
        if (!isset($files[$type]) || $files[$type]['error'] === UPLOAD_ERR_NO_FILE) {
            $issues[] = [
                'type'    => 'missing',
                'field'   => $type,
                'message' => "Missing required document: <strong>$label</strong>",
            ];
            continue;
        }

        $file = $files[$type];

        // Wrong file type
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
                $issues[] = [
                    'type'    => 'invalid_type',
                    'field'   => $type,
                    'message' => "<strong>$label</strong>: Invalid file type '.$ext'. Allowed: PDF, DOC, DOCX, JPG, PNG.",
                ];
            }

            // File too large
            $sizeMB = round($file['size'] / (1024 * 1024), 2);
            if ($file['size'] > MAX_FILE_SIZE_MB * 1024 * 1024) {
                $issues[] = [
                    'type'    => 'too_large',
                    'field'   => $type,
                    'message' => "<strong>$label</strong>: File too large ({$sizeMB}MB). Maximum is " . MAX_FILE_SIZE_MB . "MB.",
                ];
            }

            // Duplicate filename check (same user, same doc type, same original name)
            $dupCheck = $pdo->prepare(
                'SELECT d.id FROM assistance_documents d
                 JOIN assistance_requests r ON r.id = d.request_id
                 WHERE r.submitted_by = ? AND d.doc_type = ? AND d.original_name = ?
                 LIMIT 1'
            );
            $dupCheck->execute([$userId, $type, $file['name']]);
            if ($dupCheck->fetch()) {
                $warnings[] = [
                    'type'    => 'duplicate',
                    'field'   => $type,
                    'message' => "<strong>$label</strong>: A file with the same name was previously submitted. Make sure this is the latest version.",
                ];
            }

            $uploaded[] = $type;
        }
    }

    return ['issues' => $issues, 'warnings' => $warnings, 'uploaded' => $uploaded];
}

/**
 * Analyze proposal completeness.
 * Returns a score (0-100) and list of suggestions.
 */
function reviewProposal(array $data): array {
    $score       = 0;
    $maxScore    = 0;
    $suggestions = [];
    $strengths   = [];

    // ── Field checks ──────────────────────────────────────
    $checks = [
        'title' => [
            'label'   => 'Activity Title',
            'weight'  => 10,
            'check'   => fn($v) => strlen(trim($v)) >= 10,
            'tip'     => 'Title is too short. Use a descriptive title (at least 10 characters).',
        ],
        'description' => [
            'label'   => 'Activity Description',
            'weight'  => 20,
            'check'   => fn($v) => str_word_count(trim($v)) >= 30,
            'tip'     => 'Description is too brief. Provide at least 30 words explaining the activity.',
        ],
        'objectives' => [
            'label'   => 'Objectives',
            'weight'  => 15,
            'check'   => fn($v) => str_word_count(trim($v)) >= 15,
            'tip'     => 'Objectives section is missing or too short. List at least 2-3 clear objectives.',
        ],
        'expected_outcome' => [
            'label'   => 'Expected Outcomes',
            'weight'  => 15,
            'check'   => fn($v) => str_word_count(trim($v)) >= 10,
            'tip'     => 'Expected outcomes are missing. Describe what results you expect from this activity.',
        ],
        'target_date' => [
            'label'   => 'Proposed Date',
            'weight'  => 10,
            'check'   => fn($v) => !empty($v) && strtotime($v) > time(),
            'tip'     => 'Proposed date is missing or is in the past. Set a future date for the activity.',
        ],
        'target_venue' => [
            'label'   => 'Venue',
            'weight'  => 5,
            'check'   => fn($v) => strlen(trim($v)) >= 5,
            'tip'     => 'Venue is not specified. Provide the location of the activity.',
        ],
        'participants' => [
            'label'   => 'Target Participants',
            'weight'  => 10,
            'check'   => fn($v) => (int)$v > 0,
            'tip'     => 'Number of target participants is missing or zero.',
        ],
        'budget_requested' => [
            'label'   => 'Estimated Budget',
            'weight'  => 5,
            'check'   => fn($v) => (float)$v > 0,
            'tip'     => 'Estimated budget is not provided. Include a budget estimate even if approximate.',
        ],
        'activity_type' => [
            'label'   => 'Activity Type',
            'weight'  => 5,
            'check'   => fn($v) => !empty(trim($v)),
            'tip'     => 'Activity type is not selected.',
        ],
        'representative_name' => [
            'label'   => 'Representative Name',
            'weight'  => 5,
            'check'   => fn($v) => strlen(trim($v)) >= 3,
            'tip'     => 'Representative name is missing.',
        ],
    ];

    foreach ($checks as $field => $cfg) {
        $maxScore += $cfg['weight'];
        $val = $data[$field] ?? '';
        if ($cfg['check']($val)) {
            $score += $cfg['weight'];
            $strengths[] = $cfg['label'];
        } else {
            $suggestions[] = [
                'field'   => $field,
                'label'   => $cfg['label'],
                'message' => $cfg['tip'],
                'weight'  => $cfg['weight'],
            ];
        }
    }

    // ── Keyword analysis on description ───────────────────
    $desc = strtolower($data['description'] ?? '');
    $missingKeywords = [];

    $keywordGroups = [
        'methodology'  => ['how','method','approach','conduct','implement','process','procedure','step'],
        'beneficiaries'=> ['youth','beneficiar','participant','target','community','barangay','member'],
        'timeline'     => ['schedule','timeline','duration','day','week','month','hour','period'],
    ];

    foreach ($keywordGroups as $group => $keywords) {
        $found = false;
        foreach ($keywords as $kw) {
            if (str_contains($desc, $kw)) { $found = true; break; }
        }
        if (!$found) {
            $missingKeywords[] = $group;
        }
    }

    if (in_array('methodology', $missingKeywords)) {
        $suggestions[] = [
            'field'   => 'description',
            'label'   => 'Implementation Method',
            'message' => 'Your description does not mention how the activity will be conducted. Add implementation steps or methodology.',
            'weight'  => 0,
        ];
    }
    if (in_array('timeline', $missingKeywords)) {
        $suggestions[] = [
            'field'   => 'description',
            'label'   => 'Activity Timeline',
            'message' => 'Consider adding a timeline or schedule within your description.',
            'weight'  => 0,
        ];
    }

    $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100) : 0;

    $rating = match(true) {
        $percentage >= 90 => ['Excellent', '#2e7d32', '✓'],
        $percentage >= 75 => ['Good',      '#1565c0', '✓'],
        $percentage >= 55 => ['Fair',      '#f57f17', '!'],
        default           => ['Needs Work','#c62828', '✗'],
    };

    return [
        'score'       => $percentage,
        'rating'      => $rating[0],
        'color'       => $rating[1],
        'icon'        => $rating[2],
        'strengths'   => $strengths,
        'suggestions' => $suggestions,
    ];
}

/**
 * Run full check and return combined result.
 */
function runSmartCheck(array $postData, array $files, PDO $pdo, int $userId): array {
    $docResult      = checkDocuments($files, $pdo, $userId);
    $proposalResult = reviewProposal($postData);

    $canSubmit = empty($docResult['issues']) && $proposalResult['score'] >= 40;

    return [
        'can_submit'   => $canSubmit,
        'doc_issues'   => $docResult['issues'],
        'doc_warnings' => $docResult['warnings'],
        'doc_uploaded' => $docResult['uploaded'],
        'proposal'     => $proposalResult,
    ];
}
