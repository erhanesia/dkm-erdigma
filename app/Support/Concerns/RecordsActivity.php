<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Audit trail defaults shared by every model the DKM board can edit.
 *
 * Models only need to declare `$auditable` (the columns worth tracking); the
 * spatie wiring stays here so the same `getActivitylogOptions()` body is not
 * copy-pasted across seventeen models.
 */
trait RecordsActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->auditableAttributes())
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName($this->activityLogName())
            ->setDescriptionForEvent(fn (string $eventName): string => match ($eventName) {
                'created' => 'Data dibuat',
                'updated' => 'Data diperbarui',
                'deleted' => 'Data dihapus',
                'restored' => 'Data dipulihkan',
                default => $eventName,
            });
    }

    /**
     * @return array<int, string>
     */
    protected function auditableAttributes(): array
    {
        /** @var array<int, string> $auditable */
        $auditable = property_exists($this, 'auditable') ? $this->auditable : ['*'];

        return $auditable;
    }

    protected function activityLogName(): string
    {
        return class_basename($this);
    }
}
