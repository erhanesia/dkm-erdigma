<?php

declare(strict_types=1);

namespace App\Services\Audio;

use App\Enums\AudioTrackType;
use App\Exceptions\BusinessRuleException;
use App\Models\AudioTrack;
use App\Models\User;
use App\Repositories\Contracts\AudioTrackRepositoryInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Upload and lifecycle of adhan/murottal audio files.
 *
 * Files live on a private disk and are only reachable through the
 * device-authenticated stream endpoint, so the library is not publicly
 * enumerable.
 */
class AudioTrackService
{
    public function __construct(
        private readonly AudioTrackRepositoryInterface $tracks,
    ) {}

    /**
     * @return LengthAwarePaginator<int, AudioTrack>
     */
    public function paginate(): LengthAwarePaginator
    {
        return $this->tracks->paginateFiltered();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, UploadedFile $file, User $uploader): AudioTrack
    {
        $type = AudioTrackType::from((string) $attributes['type']);
        $path = $this->storeFile($file, $type);

        return DB::transaction(function () use ($attributes, $file, $path, $type, $uploader): AudioTrack {
            $isDefault = (bool) ($attributes['is_default'] ?? false);

            if ($isDefault) {
                $this->tracks->clearDefaultFlag($type);
            }

            return $this->tracks->create([
                ...$attributes,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'duration_seconds' => $attributes['duration_seconds'] ?? null,
                'uploaded_by' => $uploader->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(AudioTrack $track, array $attributes, ?UploadedFile $file = null): AudioTrack
    {
        return DB::transaction(function () use ($track, $attributes, $file): AudioTrack {
            $type = AudioTrackType::from((string) ($attributes['type'] ?? $track->type->value));

            if ((bool) ($attributes['is_default'] ?? false)) {
                $this->tracks->clearDefaultFlag($type, $track->id);
            }

            if ($file !== null) {
                $this->deleteFile($track->file_path);

                $attributes = [
                    ...$attributes,
                    'file_path' => $this->storeFile($file, $type),
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ];
            }

            return $this->tracks->update($track, $attributes);
        });
    }

    public function delete(AudioTrack $track): void
    {
        if ($this->isInUse($track)) {
            throw new BusinessRuleException(
                'Audio ini masih dipakai pada pengaturan zona atau jadwal murottal. Ganti dulu audionya sebelum menghapus.',
            );
        }

        $path = $track->file_path;

        $this->tracks->delete($track);
        $this->deleteFile($path);
    }

    public function markAsDefault(AudioTrack $track): AudioTrack
    {
        return DB::transaction(function () use ($track): AudioTrack {
            $this->tracks->clearDefaultFlag($track->type, $track->id);

            return $this->tracks->update($track, ['is_default' => true]);
        });
    }

    /**
     * Raw bytes for the device streaming endpoint.
     */
    public function readStream(AudioTrack $track): mixed
    {
        $disk = $this->disk();

        if (! $disk->exists($track->file_path)) {
            throw new BusinessRuleException('Berkas audio tidak ditemukan di penyimpanan.', 404);
        }

        return $disk->readStream($track->file_path);
    }

    public function fileSize(AudioTrack $track): int
    {
        return $this->disk()->size($track->file_path);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function groupedOptions(): array
    {
        return $this->tracks->groupedOptions();
    }

    /**
     * @return array<int, string>
     */
    public function optionsForTypes(AudioTrackType ...$types): array
    {
        return $this->tracks->optionsForTypes(...$types);
    }

    private function isInUse(AudioTrack $track): bool
    {
        return $track->zonePrayerSettingsAsAdhan()->exists()
            || $track->zonePrayerSettingsAsTarhim()->exists()
            || $track->zonePrayerSettingsAsIqamah()->exists()
            || $track->murottalSchedules()->exists();
    }

    private function storeFile(UploadedFile $file, AudioTrackType $type): string
    {
        $name = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();

        return $this->disk()->putFileAs($type->value, $file, $name);
    }

    private function deleteFile(?string $path): void
    {
        if ($path !== null && $this->disk()->exists($path)) {
            $this->disk()->delete($path);
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('dkm.audio.disk'));
    }
}
