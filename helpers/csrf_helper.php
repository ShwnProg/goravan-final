<?php
function csrf_token()
{
    return $_SESSION['csrf_token'] ?? '';
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" id="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_check()
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) &&
        hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token']);
}
?>
