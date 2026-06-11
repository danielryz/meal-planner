<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Http\Response;
use App\Repositories\MediaRepository;

final class MediaController extends AppController
{
    private const MAX_SIZE         = 10 * 1024 * 1024;
    private const ALLOWED          = ['image/jpeg', 'image/png', 'image/webp'];
    private const EXTENSIONS       = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const SUBDIRS          = ['profile_avatar' => 'avatars', 'recipe_photo' => 'recipes'];
    private const VIDEO_MAX_SIZE   = 500 * 1024 * 1024;
    private const VIDEO_ALLOWED    = ['video/mp4', 'video/quicktime', 'video/webm'];
    private const VIDEO_EXTENSIONS = ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'];

    public function uploadAvatar(): Response
    {
        if ($guard = $this->requireLogin()) {
            return $guard;
        }

        $upload = $this->processUpload('profile_avatar');

        if ($upload instanceof Response) {
            return $upload;
        }

        $userId = $this->sessions->currentUser()->id();
        $db     = new Database();
        $repo   = new MediaRepository($db->connection());

        $oldFileId   = $repo->findOldAvatarFileId($userId);
        $mediaFileId = $repo->storeFileRecord($this->buildRecord($upload, $userId, 'profile_avatar'));
        $repo->setUserAvatar($userId, $mediaFileId);

        if ($oldFileId !== null) {
            $repo->softDelete($oldFileId);
        }

        return Response::json([
            'publicId' => $upload['publicId'],
            'url'      => $this->publicUrl($upload),
        ], 201);
    }

    public function deleteAvatar(): Response
    {
        if ($guard = $this->requireLogin()) {
            return $guard;
        }

        $userId = $this->sessions->currentUser()->id();
        $db     = new Database();
        $repo   = new MediaRepository($db->connection());

        $fileId = $repo->findOldAvatarFileId($userId);

        if ($fileId === null) {
            return $this->jsonError('Brak avatara do usunięcia.', 404);
        }

        $repo->clearUserAvatar($userId);
        $repo->softDelete($fileId);

        return Response::json(['success' => true]);
    }

    public function uploadRecipePhoto(): Response
    {
        if ($guard = $this->requireLogin()) {
            return $guard;
        }

        $upload = $this->processUpload('recipe_photo');

        if ($upload instanceof Response) {
            return $upload;
        }

        $userId      = $this->sessions->currentUser()->id();
        $db          = new Database();
        $repo        = new MediaRepository($db->connection());
        $mediaFileId = $repo->storeFileRecord($this->buildRecord($upload, $userId, 'recipe_photo'));

        return Response::json([
            'mediaId'  => $mediaFileId,
            'publicId' => $upload['publicId'],
            'url'      => $this->publicUrl($upload),
        ], 201);
    }

    public function uploadRecipeVideo(): Response
    {
        if ($guard = $this->requireLogin()) {
            return $guard;
        }

        $file = $_FILES['video'] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $code = is_array($file) ? ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
            return $this->jsonError($this->uploadError($code));
        }

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::VIDEO_ALLOWED, true)) {
            return $this->jsonError('Nieobsługiwany format pliku. Wybierz MP4, MOV lub WebM.');
        }

        if ($file['size'] > self::VIDEO_MAX_SIZE) {
            return $this->jsonError('Plik jest za duży. Maksymalny rozmiar to 500 MB.');
        }

        $publicId   = $this->uuid();
        $ext        = self::VIDEO_EXTENSIONS[$mimeType];
        $storedPath = 'public/media/videos/' . $publicId . '.' . $ext;
        $absPath    = $this->root() . '/' . $storedPath;

        if (!is_dir(dirname($absPath))) {
            mkdir(dirname($absPath), 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            return $this->jsonError('Nie udało się zapisać pliku.', 500);
        }

        $checksum = hash_file('sha256', $absPath);
        $userId   = $this->sessions->currentUser()->id();
        $db       = new Database();
        $repo     = new MediaRepository($db->connection());

        $mediaFileId = $repo->storeFileRecord([
            'public_id'         => $publicId,
            'owner_user_id'     => $userId,
            'original_filename' => basename($file['name']),
            'stored_path'       => $storedPath,
            'mime_type'         => $mimeType,
            'media_type'        => 'video',
            'purpose'           => 'recipe_video',
            'size_bytes'        => $file['size'],
            'width'             => null,
            'height'            => null,
            'checksum_sha256'   => $checksum,
            'is_public'         => false,
        ]);

        return Response::json([
            'mediaId'  => $mediaFileId,
            'publicId' => $publicId,
            'url'      => '/public/media/videos/' . $publicId . '.' . $ext,
        ], 201);
    }

    private function processUpload(string $purpose): array|Response
    {
        $file = $_FILES['photo'] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $code = is_array($file) ? ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
            return $this->jsonError($this->uploadError($code));
        }

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED, true)) {
            return $this->jsonError('Nieobsługiwany format pliku. Wybierz JPG, PNG lub WebP.');
        }

        if ($file['size'] > self::MAX_SIZE) {
            return $this->jsonError('Plik jest za duży. Maksymalny rozmiar to 10 MB.');
        }

        $publicId    = $this->uuid();
        $ext         = self::EXTENSIONS[$mimeType];
        $subdir      = self::SUBDIRS[$purpose];
        $storedPath  = 'public/media/' . $subdir . '/' . $publicId . '.' . $ext;
        $absPath     = $this->root() . '/' . $storedPath;

        if (!is_dir(dirname($absPath))) {
            mkdir(dirname($absPath), 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            return $this->jsonError('Nie udało się zapisać pliku.', 500);
        }

        $dims = @getimagesize($absPath);

        return [
            'publicId'     => $publicId,
            'originalName' => basename($file['name']),
            'storedPath'   => $storedPath,
            'mimeType'     => $mimeType,
            'size'         => $file['size'],
            'width'        => $dims[0] ?? null,
            'height'       => $dims[1] ?? null,
            'checksum'     => hash_file('sha256', $absPath),
            'ext'          => $ext,
            'subdir'       => $subdir,
        ];
    }

    private function buildRecord(array $upload, int $userId, string $purpose): array
    {
        return [
            'public_id'         => $upload['publicId'],
            'owner_user_id'     => $userId,
            'original_filename' => $upload['originalName'],
            'stored_path'       => $upload['storedPath'],
            'mime_type'         => $upload['mimeType'],
            'media_type'        => 'image',
            'purpose'           => $purpose,
            'size_bytes'        => $upload['size'],
            'width'             => $upload['width'],
            'height'            => $upload['height'],
            'checksum_sha256'   => $upload['checksum'],
            'is_public'         => false,
        ];
    }

    private function publicUrl(array $upload): string
    {
        return '/public/media/' . $upload['subdir'] . '/' . $upload['publicId'] . '.' . $upload['ext'];
    }

    private function uuid(): string
    {
        $b    = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Plik jest za duży.',
            UPLOAD_ERR_PARTIAL                         => 'Plik przesłany tylko częściowo.',
            UPLOAD_ERR_NO_FILE                         => 'Nie przesłano pliku.',
            default                                    => 'Błąd przesyłania pliku.',
        };
    }
}
