<?php
/**
 * API — Leads & Sales CRM AJAX Endpoints
 * 
 * Handles JSON requests for lead creation, status changes, activity logging, 
 * assignment, and fetching detailed interaction trails.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

ensureSalesTablesExist();

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];
$userRole = $currentUser['role'];
$baseRole = function_exists('getRoleBaseType') ? getRoleBaseType($userRole) : $userRole;

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// EXPORT LEADS CSV / GOOGLE SHEETS BACKUP (Founder / Manager / HR)
if ($action === 'export_leads_csv' || $action === 'export_leads_backup') {
    if ($baseRole !== ROLE_FOUNDER && $baseRole !== ROLE_MANAGER && $baseRole !== ROLE_HR) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Permission denied. Only Founder, HR, and Manager can export lead backups.']);
        exit;
    }

    $rawIds = $_POST['lead_ids'] ?? $_GET['lead_ids'] ?? [];
    $scope = trim($_POST['scope'] ?? $_GET['scope'] ?? '');

    if ($scope === 'all' || $rawIds === 'all') {
        $leadsResult = getLeads([], 1, 100000);
        $leads = $leadsResult['data'];
    } elseif ($scope === 'filtered') {
        $filterArgs = [
            'assigned_to' => !empty($_GET['counsellor_id']) ? (int)$_GET['counsellor_id'] : (!empty($_POST['counsellor_id']) ? (int)$_POST['counsellor_id'] : null),
            'status' => $_GET['status'] ?? $_POST['status'] ?? '',
            'offering_type' => $_GET['offering_type'] ?? $_POST['offering_type'] ?? '',
            'search' => $_GET['search'] ?? $_POST['search'] ?? '',
            'is_manual' => isset($_GET['is_manual']) ? (int)$_GET['is_manual'] : (isset($_POST['is_manual']) ? (int)$_POST['is_manual'] : null)
        ];
        $leadsResult = getLeads($filterArgs, 1, 100000);
        $leads = $leadsResult['data'];
    } else {
        if (is_string($rawIds)) {
            $rawIds = explode(',', $rawIds);
        }
        if (empty($rawIds) && !empty($_GET['lead_id'])) {
            $rawIds = [(int)$_GET['lead_id']];
        }
        if (empty($rawIds) && !empty($_POST['lead_id'])) {
            $rawIds = [(int)$_POST['lead_id']];
        }

        $cleanIds = array_values(array_unique(array_filter(array_map('intval', (array)$rawIds), function($id) { return $id > 0; })));

        if (empty($cleanIds)) {
            $leadsResult = getLeads([], 1, 100000);
            $leads = $leadsResult['data'];
        } else {
            $leads = getLeadsByIds($cleanIds);
        }
    }

    if (empty($leads)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No leads found to export for backup.']);
        exit;
    }

    if (ob_get_level()) {
        ob_end_clean();
    }

    $filename = 'Leads_Backup_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    // UTF-8 Byte Order Mark (BOM) for correct Google Sheets and Excel rendering
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Lead ID',
        'Candidate Name',
        'Phone Number',
        'Alternate Phone',
        'Email Address',
        'Graduation / Degree',
        'City / Location',
        'Category / Type',
        'Course / Service Name',
        'Expected Revenue (INR)',
        'Calling / Lead Status',
        'Lead Source',
        'Assigned Counsellor',
        'Caller Notes / Remarks',
        'Next Follow-up Date',
        'Created Date',
        'Last Contact Date'
    ], ',', '"', "\\");

    foreach ($leads as $l) {
        fputcsv($out, [
            $l['id'],
            $l['name'],
            $l['phone'],
            $l['alternate_phone'] ?? '',
            $l['email'] ?? '',
            $l['graduation'] ?? '',
            $l['city'] ?? '',
            ucfirst($l['offering_type'] ?? 'Course'),
            $l['course_service_name'] ?? '',
            $l['expected_revenue'] ?? 0,
            str_replace('_', ' ', ucfirst($l['status'] ?? '')),
            $l['source'] ?? '',
            $l['assigned_to_name'] ?? 'Unassigned',
            $l['notes'] ?? '',
            !empty($l['next_followup_date']) ? $l['next_followup_date'] : '',
            $l['created_at'] ?? '',
            $l['last_contact_at'] ?? ''
        ], ',', '"', "\\");
    }

    fclose($out);
    exit;
}

header('Content-Type: application/json');

// GET Requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'get_lead') {
        $leadId = (int)($_GET['lead_id'] ?? 0);
        $lead = getLeadById($leadId);
        
        if (!$lead) {
            echo json_encode(['success' => false, 'message' => 'Lead not found.']);
            exit;
        }

        // Access check: Counsellors can see their assigned leads; Founder & Manager can see all
        if ($baseRole === ROLE_SALES_COUNSELLOR && (int)$lead['assigned_to'] !== $userId) {
            echo json_encode(['success' => false, 'message' => 'You do not have access to view this lead.']);
            exit;
        }

        echo json_encode(['success' => true, 'lead' => $lead]);
        exit;
    }

    if ($action === 'get_offerings') {
        $type = $_GET['type'] ?? null;
        $list = getCourseServicesList($type);
        echo json_encode(['success' => true, 'offerings' => $list]);
        exit;
    }

    if ($action === 'get_payment_info') {
        $leadId = (int)($_GET['lead_id'] ?? 0);
        $lead = getLeadById($leadId);
        if (!$lead) {
            echo json_encode(['success' => false, 'message' => 'Lead not found.']);
            exit;
        }

        if ($baseRole === ROLE_SALES_COUNSELLOR && (int)$lead['assigned_to'] !== $userId) {
            echo json_encode(['success' => false, 'message' => 'You do not have access to view payment details for this lead.']);
            exit;
        }

        $summary = getLeadPaymentSummary($leadId);
        echo json_encode([
            'success' => true,
            'payment_summary' => $summary,
            'lead' => [
                'id' => $lead['id'],
                'name' => $lead['name'],
                'phone' => $lead['phone'],
                'offering_type' => $lead['offering_type'],
                'course_service_name' => $lead['course_service_name'],
                'status' => $lead['status'],
                'expected_revenue' => (float)$lead['expected_revenue'],
                'paid_amount' => (float)$lead['paid_amount'],
                'payment_plan' => $lead['payment_plan'] ?: 'none',
                'total_installments' => (int)$lead['total_installments'],
                'payment_status' => $lead['payment_status'] ?: 'unpaid',
            ],
            'can_update' => ($baseRole === ROLE_FOUNDER || $baseRole === ROLE_MANAGER || $baseRole === ROLE_HR || ($baseRole === ROLE_SALES_COUNSELLOR && (int)$lead['assigned_to'] === $userId))
        ]);
        exit;
    }

    if ($action === 'get_sales_target') {
        $targetUserId = (int)($_GET['user_id'] ?? $userId);
        $targetMonth = trim($_GET['month'] ?? date('Y-m'));

        if ($baseRole === ROLE_SALES_COUNSELLOR && $targetUserId !== $userId) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $progressData = getMonthlySalesTargetAndProgress($targetUserId, $targetMonth);
        echo json_encode(['success' => true, 'data' => $progressData]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid GET action.']);
    exit;
}

// POST Requests: Validate CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh.']);
        exit;
    }

    try {
        // 1. ADD LEAD / RAW DATA PROSPECT (Founder, Manager, HR, and Sales Counsellor)
        if ($action === 'add_lead') {
            if (!in_array($baseRole, [ROLE_FOUNDER, ROLE_MANAGER, ROLE_HR, ROLE_SALES_COUNSELLOR])) {
                echo json_encode(['success' => false, 'message' => 'Permission denied. You do not have permission to add leads.']);
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $graduation = trim($_POST['graduation'] ?? '');
            $offeringType = in_array($_POST['offering_type'] ?? '', ['course', 'service']) ? $_POST['offering_type'] : 'course';
            $csName = trim($_POST['course_service_name'] ?? '');
            if (empty($csName)) {
                $csName = ($offeringType === 'course') ? 'Course Program (To be Counseled)' : 'IT Service (To be Discussed)';
            }

            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Candidate / Prospect name is required.']);
                exit;
            }
            if (empty($phone)) {
                echo json_encode(['success' => false, 'message' => 'Contact phone number is required.']);
                exit;
            }

            $assignedTo = $userId;
            if (($baseRole === ROLE_FOUNDER || $baseRole === ROLE_MANAGER || $baseRole === ROLE_HR) && !empty($_POST['assigned_to'])) {
                $assignedTo = (int)$_POST['assigned_to'];
            }
            $data = [
                'name' => $name,
                'email' => $email,
                'graduation' => $graduation,
                'is_manual' => isset($_POST['is_manual']) ? (int)(bool)$_POST['is_manual'] : 1,
                'phone' => $phone,
                'alternate_phone' => trim($_POST['alternate_phone'] ?? ''),
                'city' => trim($_POST['city'] ?? ''),
                'offering_type' => $offeringType,
                'course_service_id' => !empty($_POST['course_service_id']) ? (int)$_POST['course_service_id'] : null,
                'course_service_name' => $csName,
                'source' => trim($_POST['source'] ?? 'Raw Calling Data'),
                'status' => in_array($_POST['status'] ?? '', ['new_lead', 'interested', 'enrolled', 'uninterested']) ? $_POST['status'] : 'new_lead',
                'expected_revenue' => (float)($_POST['expected_revenue'] ?? 0),
                'assigned_to' => $assignedTo,
                'next_followup_date' => !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null,
                'notes' => trim($_POST['notes'] ?? '')
            ];

            $leadId = addLead($data, $userId);

            echo json_encode([
                'success' => true, 
                'message' => 'Prospect lead registered successfully!',
                'lead_id' => $leadId
            ]);
            exit;
        }

        // 1B. BULK IMPORT RAW CALLING DATA (Restricted to Founder, Manager, HR)
        if ($action === 'import_raw_leads') {
            if (!in_array($baseRole, [ROLE_FOUNDER, ROLE_MANAGER, ROLE_HR])) {
                echo json_encode(['success' => false, 'message' => 'Permission denied. Only Founder, HR, and Managers can import raw calling data.']);
                exit;
            }

            $rawText = trim($_POST['raw_data'] ?? '');
            if (empty($rawText) && !empty($_FILES['lead_file']['tmp_name']) && is_uploaded_file($_FILES['lead_file']['tmp_name'])) {
                $rawText = file_get_contents($_FILES['lead_file']['tmp_name']);
            }
            $requestedOfferingType = trim($_POST['offering_type'] ?? 'course');
            $defaultSource = trim($_POST['source'] ?? 'Raw Calling List');
            $assignMode = trim($_POST['assign_mode'] ?? 'single'); // 'auto_distribute', 'single', 'unassigned'

            $distributeIds = [];
            if ($baseRole === ROLE_FOUNDER || $baseRole === ROLE_MANAGER || $baseRole === ROLE_HR) {
                if ($assignMode === 'auto_distribute' || $assignMode === 'round_robin') {
                    $rawDist = $_POST['distribute_counsellors'] ?? [];
                    if (is_string($rawDist)) {
                        $rawDist = explode(',', $rawDist);
                    }
                    if (is_array($rawDist)) {
                        $distributeIds = array_values(array_unique(array_filter(array_map('intval', $rawDist), function($id) { return $id > 0; })));
                    }
                    // Fallback to all active sales counsellors if none selected
                    if (empty($distributeIds)) {
                        $activeCounsellors = getSalesCounsellorsList();
                        foreach ($activeCounsellors as $ac) {
                            $distributeIds[] = (int)$ac['id'];
                        }
                    }
                } elseif ($assignMode === 'unassigned') {
                    $singleAssignee = null;
                } else {
                    $singleAssignee = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : $userId;
                }
            } else {
                $singleAssignee = $userId;
            }

            if (empty($rawText)) {
                echo json_encode(['success' => false, 'message' => 'Please paste raw data text to import.']);
                exit;
            }

            $lines = preg_split('/\r\n|\r|\n/', $rawText);
            $importedCount = 0;
            $courseImportCount = 0;
            $serviceImportCount = 0;
            $errors = [];
            $distributionBreakdown = [];

            if (!empty($distributeIds)) {
                foreach ($distributeIds as $dId) {
                    $distributionBreakdown[$dId] = 0;
                }
            }

            // Keyword arrays for Smart Auto-Detect
            $serviceKeywords = [
                'website', 'web dev', 'web design', 'app dev', 'mobile app', 'android app', 'ios app', 
                'software', 'custom software', 'erp', 'crm', 'seo', 'digital marketing', 'marketing', 
                'graphic design', 'branding', 'logo', 'cloud', 'hosting', 'server', 'api integration', 
                'billing software', 'pos', 'inventory', 'ecommerce', 'e-commerce', 'shopify', 'wordpress', 
                'woocommerce', 'company', 'pvt ltd', 'llp', 'inc', 'enterprise', 'startup', 'client', 
                'b2b', 'it service', 'consulting', 'automation', 'portal', 'redesign', 'maintenance'
            ];

            $courseKeywords = [
                'b.tech', 'btech', 'bca', 'mca', 'b.sc', 'bsc', 'm.sc', 'msc', 'b.e', 'be', 'm.tech', 'mtech', 
                'diploma', '12th', 'hsc', 'ssc', 'passout', 'passed out', 'student', 'fresher', 'internship', 
                'intern', 'training', 'admission', 'course', 'syllabus', 'counsel', 'placement', 'college', 
                'university', 'semester', 'python', 'java', 'react', 'mern', 'mean', 'full stack', 'fullstack', 
                'data science', 'data analytics', 'ai/ml', 'machine learning', 'artificial intelligence', 
                'ui/ux', 'flutter course', 'testing course', 'devops course', 'cyber security', 'cloud course'
            ];

            foreach ($lines as $lineNum => $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // 0. Automatically skip table header rows (e.g. "Sr No, Name, Phone, Email, Degree")
                $lowerLine = strtolower($line);
                if (preg_match('/(name|mobile|phone|contact|candidate|degree|qualification|sr.?no|s.no|status)/i', $lowerLine) && !preg_match('/[0-9]{7,}/', $line)) {
                    continue;
                }

                // Clean outer quotes if whole line is quoted
                $line = preg_replace('/^["\']|["\']$/', '', $line);

                // Split by format
                $parts = [];
                if (strpos($line, "\t") !== false) {
                    $parts = explode("\t", $line);
                } elseif (strpos($line, ",") !== false) {
                    $parts = str_getcsv($line, ",", '"', "\\");
                } elseif (strpos($line, "|") !== false) {
                    $parts = explode("|", $line);
                } elseif (strpos($line, ";") !== false) {
                    $parts = explode(";", $line);
                } elseif (preg_match('/\s+-\s+/', $line)) {
                    $parts = preg_split('/\s+-\s+/', $line);
                } else {
                    // Space-separated: extract phone and split before/after
                    if (preg_match('/(\+?91[\-\s]?)?[6-9]\d{9}|\b\d{10}\b/', $line, $m, PREG_OFFSET_CAPTURE)) {
                        $phoneMatch = $m[0][0];
                        $offset = $m[0][1];
                        $before = trim(substr($line, 0, $offset));
                        $after = trim(substr($line, $offset + strlen($phoneMatch)));
                        $parts = array_filter([$before, $phoneMatch, $after], function($v) { return $v !== ''; });
                    } else {
                        $parts = [$line];
                    }
                }

                $parts = array_values(array_filter(array_map(function($v) {
                    return trim(trim($v), "\"'");
                }, $parts), function($v) { return $v !== ''; }));

                if (empty($parts)) continue;

                // Strip leading serial number (1-4 digits only, e.g. '1', '1.', '1)', '#1', '10')
                if (!empty($parts) && preg_match('/^#?\d{1,4}[\.\)]?$/', $parts[0])) {
                    array_shift($parts);
                }

                $pName = '';
                $pPhone = '';
                $pEmail = '';
                $pGrad = '';

                // 1. Identify Email
                foreach ($parts as $k => $part) {
                    if (filter_var($part, FILTER_VALIDATE_EMAIL) || (strpos($part, '@') !== false && strpos($part, '.') !== false)) {
                        $pEmail = $part;
                        unset($parts[$k]);
                        break;
                    }
                }
                $parts = array_values($parts);

                // 2. Identify Phone Number from discrete parts
                foreach ($parts as $k => $part) {
                    $digitsOnly = preg_replace('/[^0-9]/', '', $part);
                    if (strlen($digitsOnly) >= 10 && strlen($digitsOnly) <= 15) {
                        if (preg_match('/^[0-9\+\-\(\)\s\.\/]{7,}$/', $part)) {
                            $pPhone = $part;
                            unset($parts[$k]);
                            break;
                        }
                    }
                }
                $parts = array_values($parts);

                // Fallback: If no phone found yet, search within parts
                if (empty($pPhone)) {
                    foreach ($parts as $k => $part) {
                        if (preg_match('/(\+?91[\-\s]?)?[6-9]\d{9}|\b\d{10}\b/', $part, $m)) {
                            $pPhone = $m[0];
                            $cleaned = trim(str_replace($pPhone, '', $part));
                            if (empty($cleaned)) unset($parts[$k]);
                            else $parts[$k] = $cleaned;
                            break;
                        }
                    }
                    $parts = array_values($parts);
                }

                // Clean any leading serial numbers from the first remaining text (e.g. '2. Priya Nair' -> 'Priya Nair')
                if (!empty($parts)) {
                    $parts[0] = preg_replace('/^#?\d{1,4}[\.\)\-]\s*/', '', $parts[0]);
                }

                // 3. Name is the first remaining text part
                if (!empty($parts)) {
                    $pName = array_shift($parts);
                }

                // 4. Graduation, City, or Remarks from any remaining text parts
                if (!empty($parts)) {
                    $pGrad = implode(' • ', $parts);
                }

                // Fallback: If name still contains phone digits
                if (empty($pPhone) && !empty($pName)) {
                    $digitsOnly = preg_replace('/[^0-9]/', '', $pName);
                    if (strlen($digitsOnly) >= 7) {
                        $pPhone = $digitsOnly;
                        $pName = trim(preg_replace('/[0-9\+\-\(\)\s]{7,}/', '', $pName));
                    }
                }

                if (empty($pName) || empty($pPhone)) {
                    $errors[] = "Line " . ($lineNum + 1) . ": Name and Phone required. ('{$line}')";
                    continue;
                }

                // Determine Offering Type (Courses vs IT Services)
                $finalOfferingType = 'course';
                if ($requestedOfferingType === 'service') {
                    $finalOfferingType = 'service';
                } elseif ($requestedOfferingType === 'course') {
                    $finalOfferingType = 'course';
                } else {
                    // Smart Auto-Detect Mode
                    $haystack = strtolower($pName . ' ' . $pGrad . ' ' . $pEmail . ' ' . $line);
                    $matchedService = false;
                    foreach ($serviceKeywords as $sk) {
                        if (strpos($haystack, $sk) !== false) {
                            $matchedService = true;
                            break;
                        }
                    }
                    if ($matchedService) {
                        $finalOfferingType = 'service';
                    } else {
                        $finalOfferingType = 'course';
                    }
                }

                if ($finalOfferingType === 'service') {
                    $serviceImportCount++;
                } else {
                    $courseImportCount++;
                }

                // Determine assignee for this lead
                if (!empty($distributeIds)) {
                    $currentAssignee = $distributeIds[$importedCount % count($distributeIds)];
                    $distributionBreakdown[$currentAssignee]++;
                } elseif ($assignMode === 'unassigned') {
                    $currentAssignee = null;
                } else {
                    $currentAssignee = $singleAssignee;
                }

                $leadData = [
                    'name' => $pName,
                    'graduation' => $pGrad,
                    'phone' => $pPhone,
                    'email' => $pEmail,
                    'is_manual' => 1,
                    'offering_type' => $finalOfferingType,
                    'course_service_name' => ($finalOfferingType === 'course') ? 'Course Program (To be Counseled)' : 'IT Service (To be Discussed)',
                    'source' => $defaultSource,
                    'status' => 'new_lead',
                    'assigned_to' => $currentAssignee,
                    'notes' => 'Imported from raw calling data list.'
                ];

                addLead($leadData, $userId);
                $importedCount++;
            }

            if ($requestedOfferingType === 'auto_detect' || $requestedOfferingType === 'auto') {
                $successMsg = "Successfully imported {$importedCount} leads ({$courseImportCount} Courses, {$serviceImportCount} IT Services)!";
            } else {
                $categoryLabel = ($requestedOfferingType === 'service') ? 'IT Services' : 'Courses';
                $successMsg = "Successfully imported {$importedCount} {$categoryLabel} leads!";
            }

            if ($importedCount > 0 && !empty($distributeIds)) {
                $successMsg .= " (Auto-divided equally among " . count($distributeIds) . " team members)";
            } elseif ($importedCount > 0 && $assignMode === 'unassigned') {
                $successMsg .= " (Sent to unassigned calling pool)";
            }

            echo json_encode([
                'success' => $importedCount > 0, 
                'message' => $importedCount > 0 ? $successMsg : "No valid leads could be parsed. Please check format (e.g. Name, Phone, Graduation).",
                'imported_count' => $importedCount,
                'course_count' => $courseImportCount,
                'service_count' => $serviceImportCount,
                'breakdown' => $distributionBreakdown,
                'errors' => $errors
            ]);
            exit;
        }

        // 1C. CONVERT RAW PROSPECT TO COURSE / SERVICE LEAD
        if ($action === 'convert_raw_lead') {
            $leadId = (int)($_POST['lead_id'] ?? 0);
            $offeringType = in_array($_POST['offering_type'] ?? '', ['course', 'service']) ? $_POST['offering_type'] : 'course';
            $csId = !empty($_POST['course_service_id']) ? (int)$_POST['course_service_id'] : null;
            $csName = trim($_POST['course_service_name'] ?? '');
            $revenue = (float)($_POST['expected_revenue'] ?? 0);
            $newStatus = in_array($_POST['status'] ?? '', ['interested', 'enrolled', 'new_lead']) ? $_POST['status'] : 'interested';
            $notes = trim($_POST['notes'] ?? '');

            if ($leadId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid lead ID.']);
                exit;
            }

            if (empty($csName)) {
                $csName = ($offeringType === 'course') ? 'Course Program' : 'IT Service Project';
            }

            $lead = getLeadById($leadId);
            if (!$lead) {
                echo json_encode(['success' => false, 'message' => 'Lead not found.']);
                exit;
            }

            if ($baseRole === ROLE_SALES_COUNSELLOR && (int)$lead['assigned_to'] !== $userId) {
                echo json_encode(['success' => false, 'message' => 'Permission denied.']);
                exit;
            }

            $converted = convertRawLead($leadId, $offeringType, $csId, $csName, $revenue, $newStatus, $notes, $userId);

            echo json_encode([
                'success' => $converted,
                'message' => $converted ? 'Prospect successfully converted to active ' . ucfirst($offeringType) . ' lead!' : 'Failed to convert prospect.'
            ]);
            exit;
        }

        // 2. UPDATE STATUS & FOLLOW-UP (With Course/Service & Amount support)
        if ($action === 'update_status') {
            $leadId = (int)($_POST['lead_id'] ?? 0);
            $newStatus = trim($_POST['status'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');
            $nextFollowup = !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null;

            $offeringType = isset($_POST['offering_type']) && in_array($_POST['offering_type'], ['course', 'service']) ? $_POST['offering_type'] : null;
            $courseServiceId = isset($_POST['course_service_id']) && $_POST['course_service_id'] !== '' ? (int)$_POST['course_service_id'] : null;
            $courseServiceName = isset($_POST['course_service_name']) && trim($_POST['course_service_name']) !== '' ? trim($_POST['course_service_name']) : null;
            $expectedRevenue = isset($_POST['expected_revenue']) && $_POST['expected_revenue'] !== '' ? (float)$_POST['expected_revenue'] : null;

            if ($leadId <= 0 || !in_array($newStatus, ['new_lead', 'interested', 'enrolled', 'uninterested'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid lead status selected.']);
                exit;
            }

            $lead = getLeadById($leadId);
            if (!$lead) {
                echo json_encode(['success' => false, 'message' => 'Lead not found.']);
                exit;
            }

            // Permission: Counsellor can update own leads, Founder, Manager & HR can update all
            if ($baseRole === ROLE_SALES_COUNSELLOR && (int)$lead['assigned_to'] !== $userId) {
                echo json_encode(['success' => false, 'message' => 'Permission denied.']);
                exit;
            }

            if (empty($remarks)) {
                $remarks = 'Status updated to ' . ucfirst(str_replace('_', ' ', $newStatus));
                if ($courseServiceName) {
                    $remarks .= " (" . ucfirst($offeringType ?: 'course') . ": {$courseServiceName})";
                }
                if ($expectedRevenue !== null && $expectedRevenue > 0) {
                    $remarks .= " - Amount: ₹" . number_format($expectedRevenue);
                }
            }

            $updated = updateLeadStatus($leadId, $newStatus, $remarks, $nextFollowup, $userId, $offeringType, $courseServiceId, $courseServiceName, $expectedRevenue);

            // If changing status to enrolled or if payment plan parameters provided, save payment plan
            if ($updated && (!empty($_POST['setup_payment_plan']) || ($newStatus === 'enrolled' && !empty($_POST['payment_plan_type'])))) {
                $planType = in_array($_POST['payment_plan_type'] ?? '', ['one_time', 'installment']) ? $_POST['payment_plan_type'] : 'one_time';
                $installments = [];
                if (!empty($_POST['installments_json'])) {
                    $decoded = json_decode($_POST['installments_json'], true);
                    if (is_array($decoded)) $installments = $decoded;
                }

                if (!empty($installments)) {
                    saveLeadPaymentPlan($leadId, $planType, $installments, $userId);
                }
            }

            $paymentSummary = getLeadPaymentSummary($leadId);

            echo json_encode([
                'success' => $updated,
                'message' => $updated ? 'Lead status and details updated successfully!' : 'Failed to update status.',
                'new_status' => $newStatus,
                'status_badge' => getLeadStatusBadge($newStatus),
                'payment_summary' => $paymentSummary
            ]);
            exit;
        }

        // 3. CONFIGURE / SAVE PAYMENT PLAN (1-Time or Installments)
        if ($action === 'save_payment_plan') {
            $leadId = (int)($_POST['lead_id'] ?? 0);
            $planType = in_array($_POST['plan_type'] ?? '', ['one_time', 'installment']) ? $_POST['plan_type'] : 'one_time';

            $lead = getLeadById($leadId);
            if (!$lead) {
                echo json_encode(['success' => false, 'message' => 'Lead not found.']);
                exit;
            }

            if ($baseRole === ROLE_SALES_COUNSELLOR && (int)$lead['assigned_to'] !== $userId) {
                echo json_encode(['success' => false, 'message' => 'Permission denied. Only assigned counsellor or manager can setup payment plan.']);
                exit;
            }

            // Parse installments data
            $installments = [];
            if (!empty($_POST['installments_json'])) {
                $decoded = json_decode($_POST['installments_json'], true);
                if (is_array($decoded)) {
                    $installments = $decoded;
                }
            }

            if (empty($installments)) {
                $totalFee = (float)$lead['expected_revenue'];
                $initPaid = max(0, (float)($_POST['initial_paid_amount'] ?? 0));
                $installments = [
                    [
                        'installment_no' => 1,
                        'title' => ($planType === 'one_time') ? '1-Time Full Payment' : '1st Installment (At Admission)',
                        'amount' => $totalFee,
                        'due_date' => date('Y-m-d'),
                        'paid_amount' => $initPaid,
                        'paid_at' => $initPaid > 0 ? date('Y-m-d H:i:s') : null,
                        'payment_method' => $_POST['payment_method'] ?? 'upi',
                        'transaction_id' => $_POST['transaction_id'] ?? '',
                        'status' => $initPaid >= $totalFee && $totalFee > 0 ? 'paid' : ($initPaid > 0 ? 'partially_paid' : 'pending'),
                        'notes' => $_POST['notes'] ?? 'Initial enrollment payment'
                    ]
                ];
            }

            $saved = saveLeadPaymentPlan($leadId, $planType, $installments, $userId);
            $summary = getLeadPaymentSummary($leadId);

            echo json_encode([
                'success' => $saved,
                'message' => $saved ? 'Payment plan successfully configured!' : 'Failed to save payment plan.',
                'payment_summary' => $summary
            ]);
            exit;
        }

        // 4. RECORD / UPDATE INSTALLMENT PAYMENT
        if ($action === 'record_payment') {
            $leadId = (int)($_POST['lead_id'] ?? 0);
            $paymentId = (int)($_POST['payment_id'] ?? 0);
            $paidAmount = max(0, (float)($_POST['paid_amount'] ?? 0));
            $paidAt = !empty($_POST['paid_at']) ? $_POST['paid_at'] : date('Y-m-d H:i:s');
            $method = trim($_POST['payment_method'] ?? 'upi');
            $txnId = trim($_POST['transaction_id'] ?? '');
            $status = trim($_POST['status'] ?? 'auto');
            $notes = trim($_POST['notes'] ?? '');

            if ($leadId <= 0 || $paymentId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid payment installment record ID.']);
                exit;
            }

            $lead = getLeadById($leadId);
            if (!$lead) {
                echo json_encode(['success' => false, 'message' => 'Lead not found.']);
                exit;
            }

            if ($baseRole === ROLE_SALES_COUNSELLOR && (int)$lead['assigned_to'] !== $userId) {
                echo json_encode(['success' => false, 'message' => 'Permission denied. Only assigned sales counsellor or manager can update payments.']);
                exit;
            }

            $saved = recordLeadPayment($leadId, $paymentId, $paidAmount, $paidAt, $method, $txnId, $status, $notes, $userId);
            $summary = getLeadPaymentSummary($leadId);

            echo json_encode([
                'success' => $saved,
                'message' => $saved ? 'Payment successfully recorded and updated!' : 'Failed to record payment.',
                'payment_summary' => $summary
            ]);
            exit;
        }

        // 3. LOG ACTIVITY NOTE / CALL / WHATSAPP OUTREACH
        if ($action === 'add_activity' || $action === 'log_whatsapp_outreach') {
            $leadId = (int)($_POST['lead_id'] ?? 0);
            $type = in_array($_POST['activity_type'] ?? '', ['call', 'whatsapp', 'email', 'meeting', 'note']) ? $_POST['activity_type'] : 'whatsapp';
            $remarks = trim($_POST['remarks'] ?? '');
            $nextFollowup = !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null;
            $newStatus = trim($_POST['new_status'] ?? '');

            if ($leadId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid lead ID.']);
                exit;
            }

            $lead = getLeadById($leadId);
            if (!$lead) {
                echo json_encode(['success' => false, 'message' => 'Lead not found.']);
                exit;
            }

            if (empty($remarks)) {
                $remarks = ($type === 'whatsapp') ? 'Initiated WhatsApp outreach message' : 'Interaction recorded';
            }

            // Auto-update status if requested
            $statusUpdated = false;
            $finalStatus = $lead['status'];
            if (!empty($newStatus) && in_array($newStatus, ['new_lead', 'interested', 'enrolled', 'uninterested'])) {
                if ($lead['status'] !== $newStatus) {
                    updateLeadStatus($leadId, $newStatus, $remarks, $nextFollowup, $userId, $lead['offering_type'], (int)($lead['course_service_id'] ?? 0), $lead['course_service_name'] ?? '', (float)($lead['expected_revenue'] ?? 0));
                    $statusUpdated = true;
                    $finalStatus = $newStatus;
                }
            } elseif ($type === 'whatsapp' && $lead['status'] === 'new_lead') {
                // If it was a fresh/new lead, default sending WhatsApp advances it to 'interested'
                updateLeadStatus($leadId, 'interested', $remarks, $nextFollowup, $userId, $lead['offering_type'], (int)($lead['course_service_id'] ?? 0), $lead['course_service_name'] ?? '', (float)($lead['expected_revenue'] ?? 0));
                $statusUpdated = true;
                $finalStatus = 'interested';
            }

            $actId = addLeadActivity($leadId, $userId, $type, $remarks, null, null, $nextFollowup);

            echo json_encode([
                'success' => true,
                'message' => 'WhatsApp outreach recorded' . ($statusUpdated ? ' & status updated to ' . ucfirst($finalStatus) : '') . '!',
                'activity_id' => $actId,
                'status_updated' => $statusUpdated,
                'new_status' => $finalStatus,
                'status_badge' => getLeadStatusBadge($finalStatus)
            ]);
            exit;
        }

        // 4. ASSIGN / REASSIGN SINGLE LEAD (Founder / Manager / HR)
        if ($action === 'assign_lead') {
            if ($baseRole !== ROLE_FOUNDER && $baseRole !== ROLE_MANAGER && $baseRole !== ROLE_HR) {
                echo json_encode(['success' => false, 'message' => 'Permission denied to reassign leads.']);
                exit;
            }

            $leadId = (int)($_POST['lead_id'] ?? 0);
            $assignedTo = (int)($_POST['assigned_to'] ?? 0);

            if ($leadId <= 0 || $assignedTo <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid lead or counsellor ID.']);
                exit;
            }

            $assigned = assignLead($leadId, $assignedTo, $userId);

            echo json_encode([
                'success' => $assigned,
                'message' => $assigned ? 'Lead successfully assigned!' : 'Failed to assign lead.'
            ]);
            exit;
        }

        // 4B. BULK ASSIGN LEADS BY COUNT (संख्यानुसार वाटप / Assign N leads)
        if ($action === 'bulk_assign_by_count') {
            if ($baseRole !== ROLE_FOUNDER && $baseRole !== ROLE_MANAGER && $baseRole !== ROLE_HR) {
                echo json_encode(['success' => false, 'message' => 'Permission denied to assign leads in bulk.']);
                exit;
            }

            $count = (int)($_POST['count'] ?? 0);
            $assignMode = trim($_POST['assign_mode'] ?? 'single'); // 'single' or 'distribute'
            $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

            $distributeIds = [];
            if ($assignMode === 'distribute') {
                $rawDist = $_POST['distribute_counsellors'] ?? [];
                if (is_string($rawDist)) {
                    $rawDist = explode(',', $rawDist);
                }
                if (is_array($rawDist)) {
                    $distributeIds = array_values(array_unique(array_filter(array_map('intval', $rawDist), function($id) { return $id > 0; })));
                }
                if (empty($distributeIds)) {
                    $activeCounsellors = getSalesCounsellorsList();
                    foreach ($activeCounsellors as $ac) {
                        $distributeIds[] = (int)$ac['id'];
                    }
                }
            }

            if ($count <= 0) {
                echo json_encode(['success' => false, 'message' => 'Please enter a valid number of leads to assign (minimum 1).']);
                exit;
            }

            $filterConditions = [
                'source_pool' => trim($_POST['source_pool'] ?? 'unassigned'),
                'offering_type' => trim($_POST['offering_type'] ?? ''),
                'status' => trim($_POST['status'] ?? ''),
                'current_counsellor_id' => !empty($_POST['current_counsellor_id']) ? (int)$_POST['current_counsellor_id'] : null
            ];

            $result = bulkAssignLeadsByCount($count, $assignedTo, $distributeIds, $userId, $filterConditions);
            echo json_encode($result);
            exit;
        }

        // 4C. BULK ASSIGN SELECTED LEADS (Checkboxes)
        if ($action === 'bulk_assign_selected') {
            if ($baseRole !== ROLE_FOUNDER && $baseRole !== ROLE_MANAGER && $baseRole !== ROLE_HR) {
                echo json_encode(['success' => false, 'message' => 'Permission denied to assign leads in bulk.']);
                exit;
            }

            $leadIds = $_POST['lead_ids'] ?? [];
            if (is_string($leadIds)) {
                $leadIds = explode(',', $leadIds);
            }
            $cleanLeadIds = array_values(array_unique(array_filter(array_map('intval', (array)$leadIds), function($id) { return $id > 0; })));

            if (empty($cleanLeadIds)) {
                echo json_encode(['success' => false, 'message' => 'Please select at least one lead from the list.']);
                exit;
            }

            $assignMode = trim($_POST['assign_mode'] ?? 'single');
            $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

            $distributeIds = [];
            if ($assignMode === 'distribute') {
                $rawDist = $_POST['distribute_counsellors'] ?? [];
                if (is_string($rawDist)) {
                    $rawDist = explode(',', $rawDist);
                }
                if (is_array($rawDist)) {
                    $distributeIds = array_values(array_unique(array_filter(array_map('intval', $rawDist), function($id) { return $id > 0; })));
                }
                if (empty($distributeIds)) {
                    $activeCounsellors = getSalesCounsellorsList();
                    foreach ($activeCounsellors as $ac) {
                        $distributeIds[] = (int)$ac['id'];
                    }
                }
            }

            $result = bulkAssignSelectedLeads($cleanLeadIds, $assignedTo, $distributeIds, $userId);
            echo json_encode($result);
            exit;
        }

        // 5. DELETE LEAD (Founder / Manager / HR)
        if ($action === 'delete_lead') {
            if ($baseRole !== ROLE_FOUNDER && $baseRole !== ROLE_MANAGER && $baseRole !== ROLE_HR) {
                echo json_encode(['success' => false, 'message' => 'Permission denied to delete leads.']);
                exit;
            }

            $leadId = (int)($_POST['lead_id'] ?? 0);
            $deleted = deleteLead($leadId);

            echo json_encode([
                'success' => $deleted,
                'message' => $deleted ? 'Lead deleted successfully.' : 'Failed to delete lead.'
            ]);
            exit;
        }

        // 5B. BULK DELETE LEADS
        if ($action === 'bulk_delete_leads') {
            if ($baseRole !== ROLE_FOUNDER && $baseRole !== ROLE_MANAGER && $baseRole !== ROLE_HR) {
                echo json_encode(['success' => false, 'message' => 'Permission denied to delete leads in bulk.']);
                exit;
            }

            $leadIds = $_POST['lead_ids'] ?? [];
            if (is_string($leadIds)) {
                $leadIds = explode(',', $leadIds);
            }
            $cleanLeadIds = array_values(array_unique(array_filter(array_map('intval', (array)$leadIds), function($id) { return $id > 0; })));

            if (empty($cleanLeadIds)) {
                echo json_encode(['success' => false, 'message' => 'Please select at least one lead to delete.']);
                exit;
            }

            $deletedCount = bulkDeleteLeads($cleanLeadIds);
            echo json_encode([
                'success' => $deletedCount > 0,
                'count' => $deletedCount,
                'message' => "Successfully deleted {$deletedCount} leads."
            ]);
            exit;
        }

        // 5C. DELETE ALL LEADS (Founder Only)
        if ($action === 'delete_all_leads') {
            if ($baseRole !== ROLE_FOUNDER) {
                echo json_encode(['success' => false, 'message' => 'Permission denied. Only Founder has permission to delete all leads.']);
                exit;
            }

            $scope = trim($_POST['scope'] ?? 'all');
            $filters = [];
            if ($scope === 'filtered') {
                if (!empty($_POST['counsellor_id'])) $filters['assigned_to'] = (int)$_POST['counsellor_id'];
                if (!empty($_POST['status'])) $filters['status'] = trim($_POST['status']);
                if (!empty($_POST['offering_type'])) $filters['offering_type'] = trim($_POST['offering_type']);
                if (!empty($_POST['search'])) $filters['search'] = trim($_POST['search']);
                if (isset($_POST['is_manual'])) $filters['is_manual'] = (int)$_POST['is_manual'];
            }

            $deletedCount = deleteAllLeads($filters);
            echo json_encode([
                'success' => true,
                'count' => $deletedCount,
                'message' => "Successfully deleted all {$deletedCount} leads from the system."
            ]);
            exit;
        }

        // 6. ADD / EDIT OFFERING (Founder / Manager / HR)
        if ($action === 'save_offering') {
            if ($baseRole !== ROLE_FOUNDER && $baseRole !== ROLE_MANAGER && $baseRole !== ROLE_HR) {
                echo json_encode(['success' => false, 'message' => 'Permission denied to manage course/service offerings.']);
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            $type = in_array($_POST['type'] ?? '', ['course', 'service']) ? $_POST['type'] : 'course';
            $category = trim($_POST['category'] ?? 'General');
            $price = (float)($_POST['price'] ?? 0);
            $duration = trim($_POST['duration'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Offering name is required.']);
                exit;
            }

            $db = getDB();
            $stmt = $db->prepare("INSERT INTO course_services (name, type, category, price, duration, description) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $type, $category, $price, $duration ?: null, $description ?: null]);

            echo json_encode(['success' => true, 'message' => ucfirst($type) . ' added to catalogue successfully!']);
            exit;
        }

        // 7. SET / UPDATE MONTHLY SALES TARGET (Founder / Manager / HR)
        if ($action === 'save_sales_target') {
            if ($baseRole !== ROLE_FOUNDER && $baseRole !== ROLE_MANAGER && $baseRole !== ROLE_HR) {
                echo json_encode(['success' => false, 'message' => 'Permission denied. Only Founder and Manager can set sales targets.']);
                exit;
            }

            $targetUserId = (int)($_POST['user_id'] ?? 0);
            $targetMonth = trim($_POST['target_month'] ?? date('Y-m'));
            $targetRevenue = (float)($_POST['target_revenue'] ?? 0);
            $targetEnrollments = (int)($_POST['target_enrollments'] ?? 0);
            $targetLeads = (int)($_POST['target_leads'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');

            if ($targetUserId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Please select a sales employee / counsellor.']);
                exit;
            }

            if (!preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
                echo json_encode(['success' => false, 'message' => 'Invalid month format. Expected YYYY-MM.']);
                exit;
            }

            $saved = saveSalesTarget($targetUserId, $targetMonth, $targetRevenue, $targetEnrollments, $targetLeads, $notes, $userId);
            if ($saved) {
                $progress = getMonthlySalesTargetAndProgress($targetUserId, $targetMonth);
                echo json_encode([
                    'success' => true,
                    'message' => "Sales target for {$progress['month_label']} saved successfully!",
                    'target' => $progress
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save sales target. Please try again.']);
            }
            exit;
        }

        // 8. REFRESH MONTHLY COMMISSION & TARGET RECALCULATION (Founder / Manager)
        if ($action === 'refresh_monthly_commission') {
            if ($baseRole !== ROLE_FOUNDER && $baseRole !== ROLE_MANAGER) {
                echo json_encode(['success' => false, 'message' => 'Permission denied. Only Founder and Manager can refresh monthly commission.']);
                exit;
            }

            $targetMonth = trim($_POST['month'] ?? date('Y-m'));
            if (!preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
                $targetMonth = date('Y-m');
            }

            $refreshResult = refreshMonthlyCommissions($targetMonth, $userId);
            $formattedComm = number_format($refreshResult['total_commission']);
            
            echo json_encode([
                'success' => true,
                'message' => "Commission for {$refreshResult['month_label']} refreshed successfully! {$refreshResult['total_admissions']} Admissions · ₹{$formattedComm} Commission Pool.",
                'data' => $refreshResult
            ]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
    } catch (Throwable $e) {
        error_log("API Leads Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
}
