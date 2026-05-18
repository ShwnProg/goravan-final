<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once __DIR__ . '/../autoload.php';

if (empty($_SESSION['is_login'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$action = $_GET['action'] ?? '';
$readOnlyActions = ['get-docs'];

if (!in_array($action, $readOnlyActions, true) && !csrf_check()) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$userObj = new UserManagement($conn);

/* ── ADD ──────────────────────────────────────── */
// if ($action === 'add') {
//     $fullname = trim($_POST['fullname'] ?? '');
//     $email = strtolower(trim($_POST['email'] ?? ''));
//     $contact = trim($_POST['contact_number'] ?? '');
//     $birthdate = trim($_POST['birthdate'] ?? '');

//     if (!$fullname) {
//         _fail('Full name is required.');
//     }
//     if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
//         _fail('A valid email is required.');
//     }
//     if (!$contact) {
//         _fail('Contact number is required.');
//     }
//     if (!$birthdate) {
//         _fail('Birthdate is required.');
//     }

//     $userObj->email = $email;
//     if ($userObj->IsEmailExist()) {
//         _fail('A user with that email already exists.');
//     }

//     $userObj->fullname = $fullname;
//     $userObj->contact_number = $contact;
//     $userObj->birthdate = $birthdate;

//     $r = $userObj->AddUser();
//     $r['success'] ? _ok('User added successfully.') : _fail($r['error'] ?? 'Failed to add user.');
// }

/* ── EDIT ─────────────────────────────────────── */
if ($action === 'edit') {
    $user_id = (int) decrypt(trim($_POST['user_id'] ?? ''));
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $contact = trim($_POST['contact_number'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');

    if (!$user_id) {
        _fail('Invalid user ID.');
    }
    if (!$firstName || !$lastName) {
        _fail('First and last name are required.');
    }
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        _fail('A valid email is required.');
    }
    if (!$contact) {
        _fail('Contact number is required.');
    }
    if (!$birthdate) {
        _fail('Birthdate is required.');
    }

    $userObj->id = $user_id;
    $userObj->email = $email;

    $original = $userObj->GetUserByID();
    if (empty($original)) {
        _fail('User not found.');
    }

    $orig = $original[0];

    if ($email !== $orig['email'] && $userObj->IsEmailExistExcept()) {
        _fail('A user with that email already exists.');
    }

    $changed = $firstName !== $orig['firstname']
        || $lastName !== $orig['lastname']
        || $email !== $orig['email']
        || $contact !== ($orig['contact_number'] ?? '')
        || $birthdate !== ($orig['birthdate'] ?? '');

    if (!$changed) {
        ob_clean();
        echo json_encode(['success' => false, 'no_changes' => true, 'message' => 'No changes were made.']);
        exit;
    }

    $userObj->first_name = $firstName;
    $userObj->last_name = $lastName;
    $userObj->contact_number = $contact;
    $userObj->birthdate = $birthdate;

    $r = $userObj->EditUser();
    $r['success'] ? _ok('User updated successfully.') : _fail($r['error'] ?? 'Failed to update user.');
}

/* ── DELETE ───────────────────────────────────── */
// if ($action === 'delete') {
//     $user_id = (int) decrypt(trim($_POST['user_id'] ?? ''));
//     if (!$user_id) {
//         _fail('Invalid user ID.');
//     }

//     $userObj->id = $user_id;
//     $r = $userObj->DeleteUser();
//     $r['success'] ? _ok('User deleted successfully.') : _fail($r['error'] ?? 'Failed to delete user.');
// }

/* ── GET DOCUMENTS ────────────────────────────── */
if ($action === 'get-docs') {
    try {
        $raw = $_GET['user_id'] ?? '';
        
        if (empty($raw)) {
            throw new Exception('User ID is required.');
        }
        
        $user_id = decrypt(trim($raw));
        
        if (!$user_id || !is_numeric($user_id)) {
            throw new Exception('Invalid or corrupted user ID.');
        }
        
        $userObj->id = (int) $user_id;
        $docs = $userObj->GetVerificationDocuments();
        
        if (!$docs) {
            $docs = [];
        }
        
        $docs = array_map(function ($doc) {
            $doc['document_id_pk'] = encrypt((string) $doc['document_id_pk']);
            return $doc;
        }, $docs);
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'documents' => $docs
        ]);
        exit;
        
    } catch (Throwable $e) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

/* ── UPDATE DOCUMENT STATUS ───────────────────── */
if ($action === 'update-doc') {
    $doc_id = (int) decrypt(trim($_POST['document_id'] ?? ''));
    $status = trim(strtolower($_POST['status'] ?? ''));
    $allowed = ['approved', 'rejected'];

    if (!$doc_id) {
        _fail('Invalid document ID.');
    }
    if (!in_array($status, $allowed, true)) {
        _fail('Invalid status value.');
    }

    $r = $status === 'approved'
        ? $userObj->ApproveDocument($doc_id)
        : $userObj->RejectDocument($doc_id);

    $r['success'] ? _ok('Document ' . $status . ' successfully.') : _fail($r['error'] ?? 'Failed to update document.');
}

_fail('Invalid action.');

/* ── HELPERS ──────────────────────────────────── */
function _ok(string $msg): never
{
    ob_clean();
    echo json_encode(['success' => true, 'message' => $msg]);
    exit;
}

function _fail(string $msg): never
{
    ob_clean();
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}