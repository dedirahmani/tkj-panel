// Command tkjpanel-agentd adalah daemon root yang mengeksekusi operasi berhak-tinggi
// atas nama Panel (Laravel, non-root).
//
// Prinsip keamanan: agent HANYA mengeksekusi operasi dari daftar-putih (whitelist).
// Ia tidak pernah menerima atau menjalankan perintah shell arbitrer dari panel.
//
// STATUS: alur job-queue, whitelist, dan dekripsi payload (kompatibel Laravel AES)
// TERUJI end-to-end di Linux (WSL Debian), begitu pula handler situs/PHP-FPM, database,
// SSL, cron, SFTP, firewall, backup, monitoring, git deploy, user & kuota, serta terminal
// web (websocket -> PTY). Lihat agent/README.md untuk handler yang masih stub.
package main

import (
	"context"
	"flag"
	"log/slog"
	"os"
	"os/signal"
	"syscall"
	"time"

	"tkjpanel/agent/internal/config"
	"tkjpanel/agent/internal/fileman"
	"tkjpanel/agent/internal/operations"
	"tkjpanel/agent/internal/queue"
	"tkjpanel/agent/internal/terminal"
)

func main() {
	// Proses pembantu file manager: dijalankan agent dengan UID user situs (lihat internal/fileman).
	if len(os.Args) > 1 && os.Args[1] == "fs-helper" {
		os.Exit(fileman.RunHelper(os.Stdin, os.Stdout))
	}

	once := flag.Bool("once", false, "proses job pending sekali lalu keluar (untuk uji/cron)")
	flag.Parse()

	logger := slog.New(slog.NewTextHandler(os.Stdout, &slog.HandlerOptions{Level: slog.LevelInfo}))

	cfg, err := config.Load()
	if err != nil {
		logger.Error("gagal memuat konfigurasi", "err", err)
		os.Exit(1)
	}

	q, err := queue.Open(cfg)
	if err != nil {
		logger.Error("gagal membuka database antrean", "err", err)
		os.Exit(1)
	}
	defer q.Close()

	registry := operations.NewRegistry(logger)
	logger.Info("tkjpanel-agentd dimulai",
		"db", cfg.DatabasePath,
		"operations", registry.Count(),
		"poll_interval", cfg.PollInterval.String(),
	)

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	// Mode --once: proses batch pending sekali lalu keluar (dipakai uji & bisa via cron).
	if *once {
		processPending(ctx, logger, q, registry)
		logger.Info("selesai (--once)")
		return
	}

	// Endpoint lokal interaktif (di luar job queue): terminal web (/pty) dan file manager (/fs).
	if addr, ok := terminal.ListenAddr(); ok {
		srv := terminal.NewServer(cfg.AppKey, logger)
		srv.Handle("/fs", fileman.NewHandler(cfg.AppKey, logger))
		go func() {
			if err := srv.ListenAndServe(ctx, addr); err != nil {
				logger.Error("endpoint lokal gagal", "listen", addr, "err", err)
			}
		}()
	}

	ticker := time.NewTicker(cfg.PollInterval)
	defer ticker.Stop()

	for {
		select {
		case <-ctx.Done():
			logger.Info("tkjpanel-agentd berhenti")
			return
		case <-ticker.C:
			processPending(ctx, logger, q, registry)
		}
	}
}

// processPending mengklaim dan mengeksekusi semua job yang menunggu.
func processPending(ctx context.Context, logger *slog.Logger, q *queue.Queue, reg *operations.Registry) {
	jobs, err := q.ClaimPending(ctx, 10)
	if err != nil {
		logger.Error("gagal mengklaim job", "err", err)
		return
	}

	for _, job := range jobs {
		log := logger.With("job", job.UUID, "op", job.Operation)

		handler, ok := reg.Lookup(job.Operation)
		if !ok {
			// Pertahanan berlapis: operasi tak dikenal DITOLAK, tidak dieksekusi.
			log.Warn("operasi tidak ada di whitelist — job ditolak")
			_ = q.MarkFailed(ctx, job.ID, "operation not whitelisted")
			continue
		}

		result, err := handler(ctx, job.Payload)
		if err != nil {
			log.Error("job gagal", "err", err)
			_ = q.MarkFailed(ctx, job.ID, err.Error())
			continue
		}

		if err := q.MarkSuccess(ctx, job.ID, result); err != nil {
			log.Error("gagal menulis hasil sukses", "err", err)
			continue
		}
		log.Info("job selesai")
	}
}
