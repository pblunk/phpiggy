<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Database;
use Framework\Exceptions\ValidationException;
use App\Config\Paths;

class ReceiptService
{
    public function __construct(private Database $bd) {}

    public function validateFile(?array $file)
    {
        // Log the file details for debugging
        error_log("Uploaded File Details: " . print_r($file, true));

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            error_log("File upload error: " . ($file['error'] ?? 'No file received'));
            throw new ValidationException([
                'receipt' => ['Failed to upload file']
            ]);
        }

        $maxFileSizeMB = 3 * 1024 * 1024;

        if ($file['size'] > $maxFileSizeMB) {
            error_log("File too large: " . $file['size'] . " bytes");
            throw new ValidationException([
                'receipt' => ['File upload is too large']
            ]);
        }

        $originalFileName = $file['name'];

        // Fix: Adjusted regex to properly validate filenames
        if (!preg_match('/^[A-Za-z0-9\s._-]+$/', $originalFileName)) {
            error_log("Invalid filename: " . $originalFileName);
            throw new ValidationException([
                'receipt' => ['Invalid filename']
            ]);
        }

        $clientMimeType = $file['type'];
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];

        if (!in_array($clientMimeType, $allowedMimeTypes)) {
            error_log("Invalid file type: " . $clientMimeType);
            throw new ValidationException([
                'receipt' => ['Invalid file type']
            ]);
        }
    }

    public function upload(array $file)
    {
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFilename = bin2hex(random_bytes(16)) . "." . $fileExtension;

        $uploadPath = Paths::STORAGE_UPLOADS . "/" . $newFilename;

        // Ensure directory exists & is writable
        if (!is_dir(Paths::STORAGE_UPLOADS)) {
            error_log("Upload directory does not exist: " . Paths::STORAGE_UPLOADS);
            throw new ValidationException([
                'receipt' => ['Upload directory is missing']
            ]);
        }
        if (!is_writable(Paths::STORAGE_UPLOADS)) {
            error_log("Upload directory is not writable: " . Paths::STORAGE_UPLOADS);
            throw new ValidationException([
                'receipt' => ['Upload directory is not writable']
            ]);
        }

        // Attempt to move the uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            error_log("Failed to move uploaded file. Temp file: " . $file['tmp_name']);
            error_log("Destination path: " . $uploadPath);
            throw new ValidationException(['receipt' => ['Failed to upload file']]);
        }

        error_log("File successfully uploaded to: " . $uploadPath);
    }
}
