// Package operations adalah DAFTAR-PUTIH (whitelist) operasi yang boleh dijalankan agent.
// Registry ini adalah satu-satunya sumber kemampuan agent — operasi di luar map ini
// tidak akan pernah dieksekusi (lihat main.processPending).
//
// Nilai kunci HARUS sinkron dengan App\Enums\AgentOperation di panel.
package operations

import (
	"context"
	"log/slog"
)

// Handler mengeksekusi satu operasi dengan payload tervalidasi dan mengembalikan hasil.
type Handler func(ctx context.Context, payload map[string]any) (map[string]any, error)

type Registry struct {
	handlers map[string]Handler
	log      *slog.Logger
}

func NewRegistry(log *slog.Logger) *Registry {
	r := &Registry{handlers: make(map[string]Handler), log: log}

	// --- Situs / Web server ---
	r.register("create_vhost", r.createVhost)
	r.register("delete_vhost", r.deleteVhost)
	r.register("toggle_vhost", r.toggleVhost)
	r.register("reload_webserver", r.reloadWebserver)
	r.register("set_php_version", r.setPhpVersion)
	r.register("write_php_ini", r.writePhpIni)

	// --- SSL ---
	r.register("issue_ssl", r.issueSsl)
	r.register("renew_ssl", r.renewSsl)
	r.register("upload_ssl", r.uploadSsl)

	// --- Database ---
	r.register("create_database", r.createDatabase)
	r.register("drop_database", r.dropDatabase)
	r.register("create_db_user", r.createDbUser)
	r.register("reset_db_password", r.resetDbPassword)

	// --- User Linux / isolasi ---
	r.register("create_linux_user", r.createLinuxUser)
	r.register("delete_linux_user", r.deleteLinuxUser)
	r.register("set_disk_quota", r.setDiskQuota)
	r.register("set_sftp_access", r.setSftpAccess)

	// --- File / Cron / Firewall / Cloudflare / Sistem ---
	r.register("file_op", r.fileOp)
	r.register("write_crontab", r.writeCrontab)
	r.register("ufw_rule", r.ufwRule)
	r.register("fail2ban_action", r.fail2banAction)
	r.register("cloudflare_tunnel", r.cloudflareTunnel)
	r.register("collect_metrics", r.collectMetrics)
	r.register("service_control", r.serviceControl)

	// --- Git deploy & Backup ---
	r.register("git_deploy", r.gitDeploy)
	r.register("run_backup", r.runBackup)
	r.register("restore_backup", r.restoreBackup)

	return r
}

func (r *Registry) register(op string, h Handler) { r.handlers[op] = h }

func (r *Registry) Lookup(op string) (Handler, bool) {
	h, ok := r.handlers[op]
	return h, ok
}

func (r *Registry) Count() int { return len(r.handlers) }
