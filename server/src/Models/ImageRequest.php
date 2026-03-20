<?php

namespace App\Models;

/**
 * Represents an image request with parameters
 */
class ImageRequest
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        private string $type,
        private string $id,
        private string $size = 'medium',
        private bool $thumbnail = false,
        private array $params = []
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSize(): string
    {
        return $this->size;
    }

    public function isThumbnail(): bool
    {
        return $this->thumbnail;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function getParam(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function getSizeSuffix(): string
    {
        return match ($this->size) {
            'small', 'tiny' => $this->type === 'profile' ? '_small' : '_thumbnail',
            'large' => '_large',
            default => ''
        };
    }

    public function isValidUuid(): bool
    {
        if (in_array($this->type, ['profile', 'playlist'])) {
            return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $this->id) === 1;
        }
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'size' => $this->size,
            'thumbnail' => $this->thumbnail,
            'params' => $this->params
        ];
    }
}
