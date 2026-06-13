<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class MediaRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function storeFileRecord(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO media_files
                (public_id, owner_user_id, original_filename, stored_path, mime_type,
                 media_type, purpose, size_bytes, width, height, checksum_sha256, is_public)
             VALUES
                (:public_id, :owner_user_id, :original_filename, :stored_path, :mime_type,
                 :media_type, :purpose, :size_bytes, :width, :height, :checksum_sha256, :is_public)
             RETURNING id'
        );

        $stmt->bindValue(':public_id',         $data['public_id']);
        $stmt->bindValue(':owner_user_id',     $data['owner_user_id'],     PDO::PARAM_INT);
        $stmt->bindValue(':original_filename', $data['original_filename']);
        $stmt->bindValue(':stored_path',       $data['stored_path']);
        $stmt->bindValue(':mime_type',         $data['mime_type']);
        $stmt->bindValue(':media_type',        $data['media_type']);
        $stmt->bindValue(':purpose',           $data['purpose']);
        $stmt->bindValue(':size_bytes',        $data['size_bytes'],         PDO::PARAM_INT);
        $stmt->bindValue(':width',             $data['width'],              PDO::PARAM_INT);
        $stmt->bindValue(':height',            $data['height'],             PDO::PARAM_INT);
        $stmt->bindValue(':checksum_sha256',   $data['checksum_sha256']);
        $stmt->bindValue(':is_public',         $data['is_public'],          PDO::PARAM_BOOL);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function findOldAvatarFileId(int $userId): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT media_file_id FROM user_profile_avatars WHERE user_id = :user_id'
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();

        return $row ? (int) $row['media_file_id'] : null;
    }

    public function setUserAvatar(int $userId, int $mediaFileId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO user_profile_avatars (user_id, media_file_id)
             VALUES (:user_id, :media_file_id)
             ON CONFLICT (user_id) DO UPDATE SET
                media_file_id = EXCLUDED.media_file_id,
                updated_at    = CURRENT_TIMESTAMP'
        );
        $stmt->execute([':user_id' => $userId, ':media_file_id' => $mediaFileId]);
    }

    public function addRecipeMainPhoto(int $recipeId, int $mediaFileId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO recipe_media (recipe_id, media_file_id, media_role, position)
             VALUES (:recipe_id, :media_file_id, \'main_image\', 1)
             ON CONFLICT (recipe_id, media_role, position) DO UPDATE SET
                media_file_id = EXCLUDED.media_file_id,
                updated_at    = CURRENT_TIMESTAMP'
        );
        $stmt->execute([':recipe_id' => $recipeId, ':media_file_id' => $mediaFileId]);
    }

    public function addRecipeMainVideo(int $recipeId, int $mediaFileId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO recipe_media (recipe_id, media_file_id, media_role, position)
             VALUES (:recipe_id, :media_file_id, \'video\', 1)
             ON CONFLICT (recipe_id, media_role, position) DO UPDATE SET
                media_file_id = EXCLUDED.media_file_id,
                updated_at    = CURRENT_TIMESTAMP'
        );
        $stmt->execute([':recipe_id' => $recipeId, ':media_file_id' => $mediaFileId]);
    }

    public function clearUserAvatar(int $userId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM user_profile_avatars WHERE user_id = :user_id'
        );
        $stmt->execute([':user_id' => $userId]);
    }

    public function softDelete(int $mediaFileId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE media_files
             SET deleted_at = CURRENT_TIMESTAMP
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $mediaFileId]);
    }

    public function belongsToUser(int $mediaFileId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM media_files
             WHERE id = :id AND owner_user_id = :user_id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $mediaFileId, ':user_id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }
}
