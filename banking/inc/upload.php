<?php
const IMG_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
/** Checks an uploaded photo/signature. Returns the file extension, or null when nothing was uploaded. */
function check_image(?array $f, string $label): ?string {
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) throw new InvalidArgumentException("The $label could not be uploaded. Try a smaller file.");
    if ($f['size'] > 1024 * 1024) throw new InvalidArgumentException("The $label must be 1 MB or smaller.");
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset(IMG_TYPES[$mime]) || !@getimagesize($f['tmp_name'])) throw new InvalidArgumentException("The $label must be a JPG or PNG image.");
    return IMG_TYPES[$mime];
}
function store_image(array $f, string $ext, string $prefix): string {
    $name = $prefix . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/../uploads/cus/' . $name)) throw new RuntimeException('Could not store the image.');
    return $name;
}
function file_at(string $key, string $idx): ?array {
    if (!isset($_FILES[$key]['name'][$idx])) return null;
    $f = []; foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $p) $f[$p] = $_FILES[$key][$p][$idx];
    return $f;
}
