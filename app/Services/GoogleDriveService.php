<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GoogleDriveService
{
    protected ?Client $client = null;
    protected ?Drive $service = null;
    protected bool $isConfigured = false;
    protected ?string $rootFolderId = null;

    public function __construct()
    {
        $jsonPath = env('GOOGLE_SERVICE_ACCOUNT_JSON');
        $this->rootFolderId = env('GOOGLE_DRIVE_ROOT_FOLDER_ID');

        if ($jsonPath) {
            $fullPath = base_path($jsonPath);
            if (file_exists($fullPath)) {
                try {
                    $this->client = new Client();
                    $this->client->setAuthConfig($fullPath);
                    $this->client->addScope(Drive::DRIVE);
                    $this->service = new Drive($this->client);
                    $this->isConfigured = true;
                } catch (Throwable $e) {
                    Log::error('Google Drive Init Error: ' . $e->getMessage());
                }
            }
        }
    }

    public function isConfigured(): bool
    {
        return $this->isConfigured;
    }

    /**
     * Upload quotation PDF to Google Drive or local storage fallback.
     * Folder: PART CONSULTANTS / {NAMA_PT} / {TAHUN} / Quotation_{Nomor_Quote}.pdf
     *
     * @param string $localFilePath Path to local temp file
     * @param string $companyName
     * @param string $quotationNumber
     * @param int|string $year
     * @return array ['drive_path' => string, 'folder_id' => string|null, 'mode' => 'google_drive'|'local']
     */
    public function uploadQuotation(string $localFilePath, string $companyName, string $quotationNumber, $year = null): array
    {
        $year = $year ?: date('Y');
        $cleanQuoteNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', $quotationNumber);
        $fileName = "Quotation_{$cleanQuoteNo}.pdf";

        if ($this->isConfigured && $this->service) {
            try {
                // Ensure PT folder & Year folder in Google Drive
                $ptFolderId = $this->getOrCreateFolder($companyName, $this->rootFolderId);
                $yearFolderId = $this->getOrCreateFolder((string) $year, $ptFolderId);

                $fileMetadata = new DriveFile([
                    'name' => $fileName,
                    'parents' => [$yearFolderId],
                ]);

                $content = file_get_contents($localFilePath);
                $file = $this->service->files->create($fileMetadata, [
                    'data' => $content,
                    'mimeType' => 'application/pdf',
                    'uploadType' => 'multipart',
                    'fields' => 'id, webViewLink, webContentLink',
                ]);

                return [
                    'drive_path' => $file->webViewLink ?? "https://drive.google.com/file/d/{$file->id}/view",
                    'folder_id' => $ptFolderId,
                    'file_id' => $file->id,
                    'mode' => 'google_drive',
                ];
            } catch (Throwable $e) {
                Log::error('Google Drive Upload Failed: ' . $e->getMessage());
            }
        }

        // Local Storage Fallback
        $cleanCompany = preg_replace('/[^A-Za-z0-9_\-]/', '_', $companyName);
        $relativePath = "quotations/{$cleanCompany}/{$year}/{$fileName}";
        Storage::disk('public')->put($relativePath, file_get_contents($localFilePath));

        return [
            'drive_path' => Storage::disk('public')->url($relativePath),
            'folder_id' => null,
            'file_id' => null,
            'mode' => 'local',
        ];
    }

    /**
     * Upload PO PDF to Google Drive or local storage fallback.
     * Folder: PART CONSULTANTS / {NAMA_PT} / {TAHUN} / PO / PO_{Nomor_PO}.pdf
     */
    public function uploadPurchaseOrder(string $localFilePath, string $companyName, string $poNumber, $year = null): array
    {
        $year = $year ?: date('Y');
        $cleanPoNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', $poNumber);
        $fileName = "PO_{$cleanPoNo}.pdf";

        if ($this->isConfigured && $this->service) {
            try {
                $ptFolderId = $this->getOrCreateFolder($companyName, $this->rootFolderId);
                $yearFolderId = $this->getOrCreateFolder((string) $year, $ptFolderId);
                $poFolderId = $this->getOrCreateFolder('PO', $yearFolderId);

                $fileMetadata = new DriveFile([
                    'name' => $fileName,
                    'parents' => [$poFolderId],
                ]);

                $content = file_get_contents($localFilePath);
                $file = $this->service->files->create($fileMetadata, [
                    'data' => $content,
                    'mimeType' => 'application/pdf',
                    'uploadType' => 'multipart',
                    'fields' => 'id, webViewLink',
                ]);

                return [
                    'drive_path' => $file->webViewLink ?? "https://drive.google.com/file/d/{$file->id}/view",
                    'folder_id' => $poFolderId,
                    'file_id' => $file->id,
                    'mode' => 'google_drive',
                ];
            } catch (Throwable $e) {
                Log::error('Google Drive PO Upload Failed: ' . $e->getMessage());
            }
        }

        // Local Storage Fallback
        $cleanCompany = preg_replace('/[^A-Za-z0-9_\-]/', '_', $companyName);
        $relativePath = "purchase_orders/{$cleanCompany}/{$year}/{$fileName}";
        Storage::disk('public')->put($relativePath, file_get_contents($localFilePath));

        return [
            'drive_path' => Storage::disk('public')->url($relativePath),
            'folder_id' => null,
            'file_id' => null,
            'mode' => 'local',
        ];
    }

    /**
     * Get or create folder in Google Drive.
     */
    protected function getOrCreateFolder(string $folderName, ?string $parentId = null): ?string
    {
        if (!$this->service) {
            return null;
        }

        $query = "mimeType='application/vnd.google-apps.folder' and name='" . addslashes($folderName) . "' and trashed=false";
        if ($parentId) {
            $query .= " and '{$parentId}' in parents";
        }

        $response = $this->service->files->listFiles([
            'q' => $query,
            'spaces' => 'drive',
            'fields' => 'files(id, name)',
        ]);

        if (count($response->files) > 0) {
            return $response->files[0]->id;
        }

        // Create folder
        $folderMetadata = new DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => $parentId ? [$parentId] : [],
        ]);

        $folder = $this->service->files->create($folderMetadata, ['fields' => 'id']);
        return $folder->id;
    }
}
