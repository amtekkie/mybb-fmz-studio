<?php
/**
 * FMZ WYSIWYG — Public Image Upload Handler
 *
 * Accepts image uploads from the WYSIWYG editor, validates them,
 * saves to uploads/fmz_images/ and returns the public URL.
 *
 * Requires the user to be logged in with a valid post key.
 */

define('IN_MYBB', 1);
define('NO_PLUGINS', 1);

require_once './global.php';

// Only accept POST with AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(array('error' => 'Invalid request method.'));
}

// Must be logged in
if (!$mybb->user['uid']) {
    send_json(array('error' => 'You must be logged in to upload images.'));
}

// Verify post key
if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
    send_json(array('error' => 'Invalid security token. Please refresh the page.'));
}

// Check if image was uploaded
if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = array(
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temporary folder missing.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
    );
    $code = isset($_FILES['image']) ? $_FILES['image']['error'] : UPLOAD_ERR_NO_FILE;
    $msg = isset($errorMessages[$code]) ? $errorMessages[$code] : 'Upload error.';
    send_json(array('error' => $msg));
}

$file = $_FILES['image'];

// Validate MIME type
$allowedTypes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp');
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedTypes)) {
    send_json(array('error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP, BMP.'));
}

// Validate file extension
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExts = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp');
if (!in_array($ext, $allowedExts)) {
    send_json(array('error' => 'Invalid file extension.'));
}

// Load plugin settings for size limit
$maxSizeKB = 2048; // default 2MB
// Try to load from FMZ plugin options
if (isset($mybb->fmz_active_slug)) {
    require_once MYBB_ROOT . 'inc/plugins/fmzstudio/core.php';
    $fmzCore = new FMZStudio();
    $opts = $fmzCore->getMergedMiniPluginOptions($mybb->fmz_active_slug, 'fmz-wysiwyg');
    if (isset($opts['max_image_size_kb'])) {
        $maxSizeKB = (int) $opts['max_image_size_kb'];
    }
}

if ($file['size'] > $maxSizeKB * 1024) {
    send_json(array('error' => 'Image too large. Max size: ' . $maxSizeKB . ' KB (' . round($maxSizeKB / 1024, 1) . ' MB).'));
}

// Verify it's actually an image
$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    send_json(array('error' => 'File is not a valid image.'));
}

// Build target path: uploads/fmz_images/YYYY-MM/
$subDir = date('Y-m');
$uploadDir = MYBB_ROOT . 'uploads/fmz_images/' . $subDir;

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
    // Add an index.html to prevent directory listing
    @file_put_contents($uploadDir . '/index.html', '');
}

// Generate unique filename
$uniqueName = $mybb->user['uid'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$targetPath = $uploadDir . '/' . $uniqueName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    send_json(array('error' => 'Failed to save uploaded image.'));
}

// Build public URL (relative to forum root)
$publicUrl = $mybb->settings['bburl'] . '/uploads/fmz_images/' . $subDir . '/' . $uniqueName;

send_json(array(
    'success' => true,
    'url'     => $publicUrl,
    'width'   => $imageInfo[0],
    'height'  => $imageInfo[1],
));

function send_json($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
