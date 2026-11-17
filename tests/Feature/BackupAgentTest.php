<?php

namespace Tests\Feature;

use App\Enums\AgentOperation;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\Backup;
use App\Models\BackupDestination;
use App\Models\Database;
use App\Models\Site;
use App\Models\User;
use App\Support\BackupSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupAgentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Site $site;

    private BackupDestination $dest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'linux_username' => 'tkjadmin', 'is_active' => true]);
        $this->owner = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'budi', 'is_active' => true]);
        $this->site = Site::create([
            'user_id' => $this->owner->id, 'domain' => 'budi.sch.id', 'kind' => 'domain', 'php_version' => '8.3',
            'web_backend' => 'nginx_fpm', 'document_root' => '/home/budi/budi.sch.id/public', 'status' => 'active', 'redirect_type' => 'none',
        ]);
        $this->dest = BackupDestination::create([
            'user_id' => $this->admin->id, 'name' => 'Lokal', 'type' => 'local',
            'config' => ['folder' => '/var/backups/tkjpanel'], 'retention_daily' => 7, 'retention_weekly' => 4, 'encrypt_archive' => true,
        ]);
    }

    private function db(User $owner, string $name): Database
    {
        return Database::create(['user_id' => $owner->id, 'name' => $name, 'db_user' => $name, 'host' => 'localhost']);
    }

    public function test_run_sends_database_and_tracks_job(): void
    {
        $db = $this->db($this->owner, 'budi_web');

        $this->actingAs($this->admin)->post(route('backup.run'), [
            'site_id' => $this->site->id, 'destination_id' => $this->dest->id, 'database_id' => $db->id,
        ])->assertSessionHasNoErrors();

        $backup = Backup::firstOrFail();
        $job = AgentJob::findOrFail($backup->agent_job_id);
        $this->assertSame(AgentOperation::RunBackup, $job->operation);
        $this->assertSame('budi_web', $job->payload['database']);
        $this->assertSame('budi', $job->payload['linux_username']);
        $this->assertSame(BackupDestination::archivePassphrase(), $job->payload['destination']['passphrase']);
        $this->assertTrue($backup->include_database);
    }

    public function test_database_of_other_owner_rejected(): void
    {
        $other = User::factory()->create(['role' => UserRole::User, 'linux_username' => 'ani', 'is_active' => true]);
        $db = $this->db($other, 'ani_web');

        $this->actingAs($this->admin)->post(route('backup.run'), [
            'site_id' => $this->site->id, 'destination_id' => $this->dest->id, 'database_id' => $db->id,
        ])->assertSessionHasErrors('database_id');
        $this->assertSame(0, Backup::count());
    }

    public function test_local_folder_outside_allowed_prefixes_rejected(): void
    {
        foreach (['/etc', '/home/budi', '/var/backups', '/var/backups/../../etc'] as $folder) {
            $this->actingAs($this->admin)->post(route('backup.destinations.store'), [
                'name' => 'X', 'type' => 'local', 'retention_daily' => 7, 'retention_weekly' => 4, 'folder' => $folder,
            ])->assertSessionHasErrors('folder');
        }
        $this->actingAs($this->admin)->post(route('backup.destinations.store'), [
            'name' => 'OK', 'type' => 'local', 'retention_daily' => 7, 'retention_weekly' => 4, 'folder' => '/mnt/usb/tkj',
        ])->assertSessionHasNoErrors();
    }

    public function test_sync_applies_success_failure_and_pruning(): void
    {
        $old = Backup::create(['site_id' => $this->site->id, 'destination_id' => $this->dest->id, 'status' => 'success',
            'archive_path' => 'budi.sch.id/budi.sch.id_20261101-010000.tar.enc']);

        $ok = Backup::create(['site_id' => $this->site->id, 'destination_id' => $this->dest->id, 'status' => 'queued']);
        $okJob = AgentJob::dispatch(AgentOperation::RunBackup, ['backup_id' => $ok->id]);
        $okJob->update(['status' => JobStatus::Success, 'result' => [
            'archive_path' => 'budi.sch.id/budi.sch.id_20261117-010000.tar.enc', 'size_kb' => 2048,
            'completed_at' => '2026-11-17T01:00:05Z', 'pruned' => ['budi.sch.id/budi.sch.id_20261101-010000.tar.enc'],
        ]]);
        $ok->update(['agent_job_id' => $okJob->id]);

        $bad = Backup::create(['site_id' => $this->site->id, 'destination_id' => $this->dest->id, 'status' => 'queued']);
        $badJob = AgentJob::dispatch(AgentOperation::RunBackup, ['backup_id' => $bad->id]);
        $badJob->update(['status' => JobStatus::Failed, 'error' => 'folder situs tidak ada']);
        $bad->update(['agent_job_id' => $badJob->id]);

        $this->assertSame(['success' => 1, 'failed' => 1, 'pruned' => 1], BackupSync::run());

        $this->assertSame('success', $ok->fresh()->status);
        $this->assertSame(2048, $ok->fresh()->size_kb);
        $this->assertSame('failed', $bad->fresh()->status);
        $this->assertSame('folder situs tidak ada', $bad->fresh()->error);
        $this->assertSame('pruned', $old->fresh()->status);
        $this->assertFalse($old->fresh()->canRestore());
    }

    public function test_restore_requires_finished_archive_and_sends_full_payload(): void
    {
        $db = $this->db($this->owner, 'budi_web');
        $pending = Backup::create(['site_id' => $this->site->id, 'destination_id' => $this->dest->id, 'status' => 'queued']);
        $this->actingAs($this->admin)->post(route('backup.restore', $pending))->assertSessionHas('error');
        $this->assertSame(0, AgentJob::count());

        $done = Backup::create(['site_id' => $this->site->id, 'destination_id' => $this->dest->id, 'database_id' => $db->id,
            'status' => 'success', 'archive_path' => 'budi.sch.id/budi.sch.id_20261117-010000.tar.enc']);
        $this->actingAs($this->admin)->post(route('backup.restore', $done))->assertSessionHas('success');

        $job = AgentJob::findOrFail($done->fresh()->restore_job_id);
        $this->assertSame(AgentOperation::RestoreBackup, $job->operation);
        $this->assertSame('budi_web', $job->payload['database']);
        $this->assertSame('/home/budi/budi.sch.id/public', $job->payload['docroot']);
        $this->assertSame('budi.sch.id/budi.sch.id_20261117-010000.tar.enc', $job->payload['archive_path']);

        $this->actingAs($this->admin)->get(route('backup.index'))->assertOk()->assertSee(__('backup.restore_state.pending'));
    }
}
