<?php

namespace App\Listeners;

use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\BackupProblemNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

/**
 * Backup problems go to every active owner's bell (no email in v1). Found by Laravel's event discovery.
 */
class NotifyOwnersOfBackupProblem
{
    public function handleBackupHasFailed(BackupHasFailed $event): void
    {
        $this->notify(__('Daily backup FAILED: :error', ['error' => Str::limit($event->exception->getMessage(), 150)]));
    }

    public function handleCleanupHasFailed(CleanupHasFailed $event): void
    {
        $this->notify(__('Removing old backups failed: :error', ['error' => Str::limit($event->exception->getMessage(), 150)]));
    }

    public function handleUnhealthyBackupWasFound(UnhealthyBackupWasFound $event): void
    {
        $failure = $event->backupDestinationStatus->getHealthCheckFailure();

        $this->notify(__('Backup check failed on disk ":disk": :problem', [
            'disk' => $event->backupDestinationStatus->backupDestination()->diskName(),
            'problem' => Str::limit($failure?->exception()->getMessage() ?? __('unknown problem'), 150),
        ]));
    }

    private function notify(string $message): void
    {
        $owners = User::role(RoleName::Owner->value)->active()->get();

        Notification::send($owners, new BackupProblemNotification($message));
    }
}
