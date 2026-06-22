<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\GoogleIntegrationSettings;
use App\Models\AccountSettings;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Exception as GoogleServiceException;
use RuntimeException;

class GoogleDriveService
{
    public function __construct(
        private readonly GoogleOAuthService $oauth,
    ) {}

    public function ensureAppFolder(Integration $integration): string
    {
        $settings = is_array($integration->settings) ? $integration->settings : [];
        $existing = $settings['drive_folder_id'] ?? null;
        if (is_string($existing) && $existing !== '') {
            if ($this->fileExists($integration, $existing)) {
                return $existing;
            }
        }

        $drive = $this->drive($integration);
        $folderName = (string) config('services.google.drive_app_folder_name', 'Helmful');

        $file = new DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        $created = $drive->files->create($file, ['fields' => 'id']);

        if (! $created->getId()) {
            throw new RuntimeException('Google Drive folder could not be created.');
        }

        $settings['drive_folder_id'] = $created->getId();
        $integration->settings = $settings;
        $integration->save();

        $account = AccountSettings::getCurrent();
        $accountSettings = is_array($account->settings) ? $account->settings : [];
        $google = is_array($accountSettings['google'] ?? null) ? $accountSettings['google'] : [];
        $google['drive_folder_id'] = $created->getId();
        $accountSettings['google'] = $google;
        $account->settings = $accountSettings;
        $account->save();

        return $created->getId();
    }

    public function fileExists(Integration $integration, string $fileId): bool
    {
        try {
            $file = $this->drive($integration)->files->get($fileId, [
                'fields' => 'id,trashed',
            ]);

            return $file->getId() !== null && ! $file->getTrashed();
        } catch (GoogleServiceException $e) {
            if ($e->getCode() === 404) {
                return false;
            }

            throw $e;
        }
    }

    public function createSpreadsheet(Integration $integration, string $title, string $folderId): string
    {
        $drive = $this->drive($integration);

        $file = new DriveFile([
            'name' => $title,
            'mimeType' => 'application/vnd.google-apps.spreadsheet',
            'parents' => [$folderId],
        ]);

        $created = $drive->files->create($file, ['fields' => 'id']);

        if (! $created->getId()) {
            throw new RuntimeException('Google spreadsheet could not be created.');
        }

        return $created->getId();
    }

    private function drive(Integration $integration): Drive
    {
        $client = $this->oauth->clientForIntegration($integration);

        return new Drive($client);
    }
}
