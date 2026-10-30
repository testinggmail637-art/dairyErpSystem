<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\BackupProblemNotification;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\DbDumper\Databases\MariaDb;
use Spatie\DbDumper\Databases\MySql;

function scheduledCommand(string $command): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains((string) $event->command, $command));
}

it('schedules the backup daily at 02:00 IST, then cleanup and a health check', function () {
    expect(scheduledCommand('backup:run')?->expression)->toBe('0 2 * * *')
        ->and(scheduledCommand('backup:clean')?->expression)->toBe('30 2 * * *')
        ->and(scheduledCommand('backup:monitor')?->expression)->toBe('0 3 * * *')
        ->and(config('app.timezone'))->toBe('Asia/Kolkata');
});

it('keeps backups for 14 days on the backups disk, with database and uploads only', function () {
    $strategy = config('backup.cleanup.default_strategy');

    expect($strategy['keep_all_backups_for_days'])->toBe(14)
        ->and($strategy['keep_daily_backups_for_days'])->toBe(0)
        ->and($strategy['keep_weekly_backups_for_weeks'])->toBe(0)
        ->and($strategy['keep_monthly_backups_for_months'])->toBe(0)
        ->and($strategy['keep_yearly_backups_for_years'])->toBe(0)
        ->and(config('backup.backup.destination.disks'))->toBe(['backups'])
        ->and(config('backup.backup.source.databases'))->toBe([config('database.default')])
        ->and(config('backup.backup.source.files.include'))->toBe([
            public_path('uploads'),
            storage_path('app'.DIRECTORY_SEPARATOR.'private'),
        ])
        ->and(config('filesystems.disks.backups.root'))->toBe(storage_path('app/backups'));
});

it('sends no emails (in-app notifications only)', function () {
    expect(collect(config('backup.notifications.notifications'))->flatten()->all())->toBe([]);
});

it('tells active owners in the bell when the backup or cleanup fails', function () {
    Notification::fake();

    $owner = User::factory()->withRole(RoleName::Owner)->create();
    $inactiveOwner = User::factory()->inactive()->withRole(RoleName::Owner)->create();
    $admin = User::factory()->withRole(RoleName::Admin)->create();

    event(new BackupHasFailed(new Exception('mysqldump: Access denied')));
    event(new CleanupHasFailed(new Exception('disk full')));

    Notification::assertSentTo($owner, BackupProblemNotification::class, function ($notification) use ($owner) {
        $data = $notification->toArray($owner);

        return str_contains($data['message'], 'Daily backup FAILED: mysqldump: Access denied')
            && $notification->via($owner) === ['database'];
    });
    Notification::assertSentToTimes($owner, BackupProblemNotification::class, 2);
    Notification::assertNotSentTo([$inactiveOwner, $admin], BackupProblemNotification::class);
});

it('uses mysqldump for MariaDB when mariadb-dump is not installed (XAMPP)', function () {
    $dir = storage_path('framework/testing/dump-bin');
    @mkdir($dir, 0777, true);

    config(['database.connections.mariadb.dump.dump_binary_path' => $dir]);
    expect(DbDumperFactory::createFromConnection('mariadb'))->toBeInstanceOf(MySql::class)
        ->not->toBeInstanceOf(MariaDb::class);

    touch($dir.'/mariadb-dump');
    expect(DbDumperFactory::createFromConnection('mariadb'))->toBeInstanceOf(MariaDb::class);

    unlink($dir.'/mariadb-dump');
    @rmdir($dir);
});
