<?php

function storeVerificationIdUpload($upload, &$error)
{
    $error = '';
    if (
        !is_array($upload) ||
        !isset($upload['error'], $upload['tmp_name'], $upload['size']) ||
        !is_string($upload['tmp_name'])
    ) {
        $error = 'Choose a photo of a valid ID.';
        return false;
    }

    if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        $error = $upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'The ID photo must be no larger than 5 MB.'
            : 'The ID photo could not be uploaded. Choose it again and retry.';
        return false;
    }
    if ($upload['size'] <= 0 || $upload['size'] > 5 * 1024 * 1024) {
        $error = 'The ID photo must be no larger than 5 MB.';
        return false;
    }

    $imageInfo = @getimagesize($upload['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    if (
        !$imageInfo ||
        !isset($extensions[$mime]) ||
        !isset($imageInfo['mime']) ||
        $imageInfo['mime'] !== $mime ||
        $imageInfo[0] < 100 ||
        $imageInfo[1] < 100 ||
        $imageInfo[0] * $imageInfo[1] > 40000000
    ) {
        $error = 'Upload a clear JPG, PNG, or WebP image of your ID.';
        return false;
    }

    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'id-verification';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        error_log('Unable to create the private ID verification upload directory.');
        $error = 'ID verification is temporarily unavailable. Please try again.';
        return false;
    }

    $filename = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($upload['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        error_log('Unable to store an ID verification image.');
        $error = 'The ID photo could not be saved. Please try again.';
        return false;
    }

    return $filename;
}

function deleteVerificationIdUpload($filename)
{
    if (!is_string($filename) || !preg_match('/\A[a-f0-9]{48}\.(?:jpg|png|webp)\z/', $filename)) {
        return false;
    }
    $path = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR
        . 'id-verification' . DIRECTORY_SEPARATOR . $filename;
    return !is_file($path) || unlink($path);
}
