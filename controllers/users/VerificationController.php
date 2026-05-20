<?php
require_once '../../autoload.php';

header('Content-Type: application/json');

if (!isset($_SESSION['is_login'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$userId = (int) decrypt($_SESSION['id']);
$action = trim($_POST['action'] ?? '');

$verification             = new Verification($conn);
$verification->user_id_fk = $userId;

$current       = $verification->GetVerficationStatus();
$currentStatus = $current['status'] ?? null;
$hasApproved   = $verification->HasApprovedVerification();
$hasPending    = $verification->HasPendingVerification();

$validActions = ['submit_verification', 'resubmit_verification', 'update_verification'];
if (!in_array($action, $validActions, true)) {
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

if ($hasPending) {
    echo json_encode(['success' => false, 'message' => 'You already have a verification under review.']);
    exit;
}
if ($action === 'submit_verification' && $hasApproved) {
    echo json_encode(['success' => false, 'message' => 'Your verification is already approved. Use "Update" instead.']);
    exit;
}
if ($action === 'resubmit_verification' && ($currentStatus !== 'rejected' || $hasApproved)) {
    echo json_encode(['success' => false, 'message' => 'No rejected verification found to resubmit.']);
    exit;
}
if ($action === 'update_verification' && !$hasApproved) {
    echo json_encode(['success' => false, 'message' => 'No approved verification found to update.']);
    exit;
}

$type = strtolower(trim($_POST['verification_type'] ?? ''));
if (!in_array($type, ['student', 'senior', 'pwd'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid verification type selected.']);
    exit;
}

$userObj = new Users($conn);
$userObj->id = $userId;
$user = $userObj->GetUserById();
$birthdate = is_array($user) ? ($user['birthdate'] ?? null) : null;
$typeError = Verification::ValidateTypeForBirthdate($type, $birthdate);
if ($typeError) {
    echo json_encode(['success' => false, 'message' => $typeError]);
    exit;
}

if (!isset($_FILES['verification_document']) || $_FILES['verification_document']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server size limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds allowed size.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
    ];
    $errCode = $_FILES['verification_document']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errMsg  = $uploadErrors[$errCode] ?? 'File upload failed. Please try again.';
    echo json_encode(['success' => false, 'message' => $errMsg]);
    exit;
}

$file         = $_FILES['verification_document'];
$ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExts  = ['jpg', 'jpeg', 'png', 'pdf'];
$maxBytes     = 5 * 1024 * 1024;

if (!in_array($ext, $allowedExts, true)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and PDF files are accepted.']);
    exit;
}
if ($file['size'] > $maxBytes) {
    echo json_encode(['success' => false, 'message' => 'File size must not exceed 5 MB.']);
    exit;
}

$uploadDir = __DIR__ . '/../../uploads/documents/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    echo json_encode(['success' => false, 'message' => 'Upload directory is not available.']);
    exit;
}

// Match RegisterController.php: files live in uploads/documents and DB stores filename only.
$filename = time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
$target   = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save the document. Please try again.']);
    exit;
}

$verification->type     = $type;
$verification->document = $filename;
$verification->status   = 'pending';

$result = $verification->AddDocuments();

if ($result === true) {
    $messages = [
        'submit_verification'   => 'Verification submitted. We will review it shortly.',
        'resubmit_verification' => 'Verification resubmitted successfully.',
        'update_verification'   => 'Update submitted for review.',
    ];
    echo json_encode(['success' => true, 'message' => $messages[$action]]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
exit;
?>
