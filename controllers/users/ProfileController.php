<?php
require_once '../../autoload.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    $user = new Users($conn);
    $first_name = ucwords(trim($_POST['first_name'] ?? ''));
    $last_name = ucwords(trim($_POST['last_name'] ?? ''));
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $errors = [];


    $user->id = decrypt($_SESSION['id']);



    $old_data = $user->GetUserById();
    if (
        $old_data['firstname'] === $first_name
        && $old_data['lastname'] === $last_name
        && $old_data['email'] === $email
        && $old_data['contact_number'] === $contact
    ) {
        echo json_encode(['success' => false, 'message' => 'No changes detected']);
        exit;
    }

    // NAME
    if (empty($first_name) || strlen($first_name) < 2) {
        $errors[] = 'First name must be at least 2 characters';
    }
    if (empty($last_name) || strlen($last_name) < 2) {
        $errors[] = 'Last name must be at least 2 characters';
    }
       if(strlen($firstName) > 50) {
        $errors[] = 'First name cannot exceed 50 characters';
    }
    if(strlen($lastName) > 50) {
        $errors[] = 'Last name cannot exceed 50 characters';
    }

    // EMAIL
    $clean_email = filter_var($email, FILTER_SANITIZE_EMAIL);
    $validate_email = filter_var($clean_email, FILTER_VALIDATE_EMAIL);

    if ($validate_email === false) {
        $errors[] = 'Please enter a valid email';
    }

    if ($old_data['email'] != $email && $user->IsDuplicateEmail($email)) {
        $errors[] = 'Email is already registered';
    }

    // CONTACT
    $contact = preg_replace('/[^0-9]/', '', $contact);

    if (strlen($contact) != 11) {
        $errors[] = "Please enter a valid 11-digit contact number";
    }

    if (!empty($errors)) {
        echo json_encode(['success'=> false ,'message' => $errors]);
        exit;
    }

    $user->first_name = $first_name;
    $user->last_name = $last_name;
    $user->email = $email;
    $user->contact = $contact;


    // var_dump($first_name, $last_name, $email, $contact);

    // $user = new Users($conn);

    $result = $user->UpdateProfile();

    if ($result) {
       echo json_encode(['success' => true,'message' => 'Profile Updated Successfully']);
       exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to Update Profile']);
        exit;
    }
}
?>