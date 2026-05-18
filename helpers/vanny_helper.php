<?php

function vanny_images(): array
{
    return [
        'location' => 'vanny-location.png',
        'error' => 'vanny-error.png',
        'welcome' => 'vanny-welcome.png',
        'letsGo' => 'vanny-lets-go.png',
        'waiting' => 'vanny-waiting.png',
        'heart' => 'vanny-heart.png',
        'thankYou' => 'vanny-thank-you.png',
        'phoneBooking' => 'vanny-phone-booking.png',
        'running' => 'vanny-running.png',
        'thumbsUp' => 'vanny-thumbs-up.png',
        'excited' => 'vanny-excited.png',
        'wave' => 'vanny-wave.png',
        'pointing' => 'vanny-pointing.png',
        'celebrate' => 'vanny-celebrate.png',
        'backView' => 'vanny-back-view.png',
    ];
}

function vanny_asset_url(string $type): string
{
    $images = vanny_images();
    $filename = $images[$type] ?? $images['welcome'];
    return '/images/' . $filename;
}

function vanny_mascot(string $type = 'welcome', string $size = 'medium', string $className = '', ?string $alt = null): string
{
    $safeType = preg_replace('/[^a-zA-Z0-9_-]/', '', $type) ?: 'welcome';
    $safeSize = preg_replace('/[^a-zA-Z0-9_-]/', '', $size) ?: 'medium';
    $classes = trim("vanny-mascot vanny-mascot--{$safeSize} {$className}");
    $safeAlt = htmlspecialchars($alt ?? 'Vanny mascot', ENT_QUOTES, 'UTF-8');
    $src = htmlspecialchars(vanny_asset_url($safeType), ENT_QUOTES, 'UTF-8');

    return '<img src="' . $src . '" alt="' . $safeAlt . '" class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '" loading="lazy" decoding="async">';
}

function vanny_message_card(
    string $type = 'info',
    string $title = '',
    string $description = '',
    string $mascotType = 'pointing',
    string $actionText = '',
    string $actionHref = '',
    string $className = ''
): string {
    $safeType = preg_replace('/[^a-zA-Z0-9_-]/', '', $type) ?: 'info';
    $classes = trim("vanny-message-card vanny-message-card--{$safeType} {$className}");
    $html = '<div class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '">';
    $html .= vanny_mascot($mascotType, 'small', 'vanny-message-card__mascot', $title ?: 'Vanny message');
    $html .= '<div class="vanny-message-card__body">';
    if ($title !== '') {
        $html .= '<h3>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>';
    }
    if ($description !== '') {
        $html .= '<p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    if ($actionText !== '' && $actionHref !== '') {
        $html .= '<a class="vanny-message-card__action" href="' . htmlspecialchars($actionHref, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($actionText, ENT_QUOTES, 'UTF-8') . '</a>';
    }
    $html .= '</div></div>';

    return $html;
}

function vanny_empty_state(
    string $mascotType = 'waiting',
    string $title = 'Nothing here yet',
    string $description = '',
    string $actionText = '',
    string $actionHref = '',
    string $className = ''
): string {
    $classes = trim("vanny-empty-state {$className}");
    $html = '<div class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '">';
    $html .= vanny_mascot($mascotType, 'medium', 'vanny-empty-state__mascot', $title);
    $html .= '<h3>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>';
    if ($description !== '') {
        $html .= '<p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    if ($actionText !== '' && $actionHref !== '') {
        $html .= '<a class="vanny-empty-state__action" href="' . htmlspecialchars($actionHref, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($actionText, ENT_QUOTES, 'UTF-8')  . '</a>';
    }
    $html .= '</div>';

    return $html;
}

function vanny_alert(string $type = 'info', string $message = '', string $mascotType = 'pointing', string $className = ''): string
{
    $safeType = preg_replace('/[^a-zA-Z0-9_-]/', '', $type) ?: 'info';
    $classes = trim("vanny-alert vanny-alert--{$safeType} {$className}");

    return '<div class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '">'
        . vanny_mascot($mascotType, 'small', 'vanny-alert__mascot', 'Vanny alert')
        . '<span>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</span>'
        . '</div>';
}

function vanny_confirm_modal(
    string $id,
    string $title,
    string $description,
    string $confirmText = 'Confirm',
    string $cancelText = 'Cancel',
    string $mascotType = 'pointing'
): string {
    $safeId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');

    return '<div class="modal fade vanny-confirm-modal" id="' . $safeId . '" tabindex="-1" aria-hidden="true">'
        . '<div class="modal-dialog modal-dialog-centered">'
        . '<div class="modal-content">'
        . '<div class="modal-body">'
        . vanny_mascot($mascotType, 'medium', 'vanny-confirm-modal__mascot', $title)
        . '<h3>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>'
        . '<p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<div class="vanny-confirm-modal__actions">'
        . '<button type="button" class="btn btn-light" data-bs-dismiss="modal">' . htmlspecialchars($cancelText, ENT_QUOTES, 'UTF-8') . '</button>'
        . '<button type="button" class="btn btn-primary" data-vanny-confirm>' . htmlspecialchars($confirmText, ENT_QUOTES, 'UTF-8') . '</button>'
        . '</div></div></div></div></div>';
}
