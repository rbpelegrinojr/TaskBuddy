<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Env;
use TaskBuddy\Support\Paths;

/** Validates and stores uploads in protected storage (outside the web root, randomised names). */
final class FileService
{
    /** Tests only: allow files that were not delivered through an HTTP upload. */
    public static bool $allowNonUploaded = false;

    /** extension => [allowed finfo MIME types, signature check] */
    private const TYPES = [
        'pdf' => [['application/pdf'], 'pdf'],
        'png' => [['image/png'], 'png'],
        'jpg' => [['image/jpeg'], 'jpeg'],
        'jpeg' => [['image/jpeg'], 'jpeg'],
        'gif' => [['image/gif'], 'gif'],
        'webp' => [['image/webp'], 'webp'],
        'txt' => [['text/plain'], 'text'],
        'csv' => [['text/plain', 'text/csv', 'application/csv'], 'text'],
        'doc' => [['application/msword', 'application/x-ole-storage', 'application/CDFV2'], 'ole'],
        'xls' => [['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'], 'ole'],
        'ppt' => [['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/CDFV2'], 'ole'],
        'docx' => [['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'], 'zip'],
        'xlsx' => [['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'], 'zip'],
        'pptx' => [['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'], 'zip'],
        'zip' => [['application/zip', 'application/x-zip-compressed'], 'zip'],
    ];

    private const EXECUTABLE_EXT = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'exe', 'dll', 'com', 'bat', 'cmd', 'sh', 'bash',
        'cgi', 'pl', 'py', 'rb', 'js', 'mjs', 'jar', 'msi', 'vbs', 'ps1', 'htaccess', 'html', 'htm', 'svg', 'swf', 'apk'];

    /**
     * @param array<string,mixed> $file one $_FILES entry
     * @return array{original_name:string,stored_name:string,mime_type:string,size_bytes:int,sha256:string}
     */
    public static function store(array $file, string $bucket): array
    {
        if (!in_array($bucket, ['request', 'submission'], true)) {
            throw new \InvalidArgumentException('Unknown upload bucket.');
        }
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            throw new ApiException(413, 'FILE_TOO_LARGE', 'The uploaded file exceeds the maximum allowed size.');
        }
        if ($err !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            throw ApiException::validation(['file' => 'File upload failed or no file was provided.']);
        }
        $tmp = (string)$file['tmp_name'];
        if (!self::$allowNonUploaded && !is_uploaded_file($tmp)) {
            throw ApiException::validation(['file' => 'Invalid upload.']);
        }
        $size = (int)filesize($tmp);
        $max = Env::int('UPLOAD_MAX_BYTES', 10485760);
        if ($size <= 0) {
            throw ApiException::validation(['file' => 'The file is empty.']);
        }
        if ($size > $max) {
            throw new ApiException(413, 'FILE_TOO_LARGE', 'The uploaded file exceeds the maximum allowed size.', ['max_bytes' => $max]);
        }

        $original = self::sanitizeName((string)($file['name'] ?? 'file'));
        $parts = array_slice(explode('.', strtolower($original)), 1);
        foreach ($parts as $p) {
            if (in_array($p, self::EXECUTABLE_EXT, true)) {
                throw new ApiException(415, 'UNSUPPORTED_FILE_TYPE', 'Executable or script files are not allowed.');
            }
        }
        $ext = $parts ? end($parts) : '';
        if (!isset(self::TYPES[$ext])) {
            throw new ApiException(415, 'UNSUPPORTED_FILE_TYPE', 'This file type is not allowed.', ['allowed_extensions' => array_keys(self::TYPES)]);
        }

        $head = (string)file_get_contents($tmp, false, null, 0, 8192);
        if (preg_match('/^(MZ|\x7fELF|#!)/', $head) || stripos($head, '<?php') !== false) {
            throw new ApiException(415, 'UNSUPPORTED_FILE_TYPE', 'Executable or script content is not allowed.');
        }
        [$mimes, $sig] = self::TYPES[$ext];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
        if (!in_array($mime, $mimes, true) || !self::signatureOk($sig, $head)) {
            throw new ApiException(415, 'UNSUPPORTED_FILE_TYPE', 'The file content does not match its extension.');
        }

        $stored = bin2hex(random_bytes(20));
        $dir = self::bucketDir($bucket) . '/' . substr($stored, 0, 2);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create upload directory.');
        }
        $dest = $dir . '/' . $stored;
        $moved = self::$allowNonUploaded ? copy($tmp, $dest) : move_uploaded_file($tmp, $dest);
        if (!$moved) {
            throw new \RuntimeException('Cannot store uploaded file.');
        }
        @chmod($dest, 0640);
        return ['original_name' => $original, 'stored_name' => $stored, 'mime_type' => $mime, 'size_bytes' => $size, 'sha256' => hash_file('sha256', $dest)];
    }

    public static function delete(string $bucket, string $stored): void
    {
        $p = self::path($bucket, $stored);
        if (is_file($p)) {
            @unlink($p);
        }
    }

    /** Resolve a stored file; the name is validated so path traversal is impossible. */
    public static function path(string $bucket, string $stored): string
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $stored)) {
            throw ApiException::notFound('File');
        }
        return self::bucketDir($bucket) . '/' . substr($stored, 0, 2) . '/' . $stored;
    }

    public static function bucketDir(string $bucket): string
    {
        return Paths::storage('uploads') . '/' . $bucket;
    }

    public static function sanitizeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1f\x7f]/u', '', $name) ?? '';
        $name = trim(mb_substr($name, 0, 200));
        return $name === '' ? 'file' : $name;
    }

    private static function signatureOk(string $sig, string $head): bool
    {
        return match ($sig) {
            'pdf' => str_starts_with($head, '%PDF-'),
            'png' => str_starts_with($head, "\x89PNG\r\n\x1a\n"),
            'jpeg' => str_starts_with($head, "\xFF\xD8\xFF"),
            'gif' => str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a'),
            'webp' => str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP',
            'zip' => str_starts_with($head, "PK\x03\x04"),
            'ole' => str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
            'text' => !str_contains($head, "\0") && self::validUtf8Prefix($head),
            default => false,
        };
    }

    /** A 8 KB prefix may end in the middle of a multi-byte character; tolerate up to 3 trailing bytes. */
    private static function validUtf8Prefix(string $head): bool
    {
        for ($cut = 0; $cut <= 3 && $cut < strlen($head); $cut++) {
            if (mb_check_encoding(substr($head, 0, strlen($head) - $cut), 'UTF-8')) {
                return true;
            }
        }
        return $head === '';
    }
}
