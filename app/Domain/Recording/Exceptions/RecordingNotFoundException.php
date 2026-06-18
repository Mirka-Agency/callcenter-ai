<?php

namespace App\Domain\Recording\Exceptions;

use RuntimeException;

class RecordingNotFoundException extends RuntimeException
{
    /** @param  list<string>  $disksTried */
    public static function forPath(string $path, array $disksTried = []): self
    {
        $disks = $disksTried !== [] ? implode(', ', $disksTried) : 'none';

        return new self("Recording file not found at path [{$path}] (disks tried: {$disks}).");
    }

    public static function storageWriteFailed(string $path, string $disk): self
    {
        return new self("Failed to store recording at [{$path}] on disk [{$disk}].");
    }
}
