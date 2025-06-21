<?php

declare(strict_types=1);

namespace YFEvents\Domain\Communication\Entities;

use YakimaFinds\Domain\Common\EntityInterface;

/**
 * Attachment entity for communication file uploads
 */
class Attachment implements EntityInterface
{
    private ?int $id;
    private int $messageId;
    private string $filename;
    private string $originalFilename;
    private string $filePath;
    private int $fileSize;
    private string $mimeType;
    private bool $isImage;
    private \DateTimeImmutable $createdAt;
    
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'text/plain',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    
    private const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];
    
    public function __construct(
        ?int $id,
        int $messageId,
        string $filename,
        string $originalFilename,
        string $filePath,
        int $fileSize,
        string $mimeType,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->id = $id;
        $this->messageId = $messageId;
        $this->filename = $filename;
        $this->originalFilename = $originalFilename;
        $this->filePath = $filePath;
        $this->fileSize = $fileSize;
        $this->mimeType = $mimeType;
        $this->isImage = in_array($mimeType, self::IMAGE_MIME_TYPES, true);
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        
        $this->validate();
    }
    
    private function validate(): void
    {
        if ($this->messageId <= 0) {
            throw new \InvalidArgumentException('Invalid message ID');
        }
        
        if (empty($this->filename)) {
            throw new \InvalidArgumentException('Filename cannot be empty');
        }
        
        if (empty($this->originalFilename)) {
            throw new \InvalidArgumentException('Original filename cannot be empty');
        }
        
        if (empty($this->filePath)) {
            throw new \InvalidArgumentException('File path cannot be empty');
        }
        
        if ($this->fileSize <= 0) {
            throw new \InvalidArgumentException('Invalid file size');
        }
        
        if ($this->fileSize > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(
                sprintf('File size exceeds maximum allowed size of %d MB', self::MAX_FILE_SIZE / 1024 / 1024)
            );
        }
        
        if (!in_array($this->mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('File type not allowed');
        }
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function getMessageId(): int
    {
        return $this->messageId;
    }
    
    public function getFilename(): string
    {
        return $this->filename;
    }
    
    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }
    
    public function getFilePath(): string
    {
        return $this->filePath;
    }
    
    public function getFileSize(): int
    {
        return $this->fileSize;
    }
    
    public function getFileSizeFormatted(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->fileSize;
        $unit = 0;
        
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        
        return round($size, 2) . ' ' . $units[$unit];
    }
    
    public function getMimeType(): string
    {
        return $this->mimeType;
    }
    
    public function isImage(): bool
    {
        return $this->isImage;
    }
    
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    
    public function getFileExtension(): string
    {
        return pathinfo($this->originalFilename, PATHINFO_EXTENSION);
    }
    
    public function getWebPath(): string
    {
        return '/uploads/communication/' . $this->filename;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'message_id' => $this->messageId,
            'filename' => $this->filename,
            'original_filename' => $this->originalFilename,
            'file_path' => $this->filePath,
            'file_size' => $this->fileSize,
            'mime_type' => $this->mimeType,
            'is_image' => $this->isImage ? 1 : 0,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
    
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'original_filename' => $this->originalFilename,
            'file_size' => $this->getFileSizeFormatted(),
            'mime_type' => $this->mimeType,
            'is_image' => $this->isImage ? 1 : 0,
            'url' => $this->getWebPath(),
            'extension' => $this->getFileExtension(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
    
    public static function fromArray(array $data): static
    {
        return new self(
            isset($data['id']) ? (int)$data['id'] : null,
            (int)$data['message_id'],
            $data['filename'],
            $data['original_filename'],
            $data['file_path'],
            (int)$data['file_size'],
            $data['mime_type'],
            isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null
        );
    }
}