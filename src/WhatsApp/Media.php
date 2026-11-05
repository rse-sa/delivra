<?php

namespace RSE\Delivra\WhatsApp;

use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

class Media
{
    public function __construct(
        public readonly string $id,
        public readonly string $mimeType,
        public readonly string $contents,
    ) {}

    public function extension(): string
    {
        $mimeType = Str::before($this->mimeType, ';');

        return (class_exists(MimeTypes::class) ? (MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? null) : null) ?? 'bin';
    }

    public function size(): int
    {
        return strlen($this->contents);
    }

    /**
     * Write to a file path, or to a temp file when no path is given. Returns the path.
     */
    public function save(?string $path = null): string
    {
        $path ??= sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wa_' . $this->id . '.' . $this->extension();

        file_put_contents($path, $this->contents);

        return $path;
    }
}
