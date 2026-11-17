// Package queue mengimplementasikan sisi-agent dari protokol job (DB queue MVP).
// Agent melakukan polling tabel agent_jobs milik panel di SQLite.
package queue

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
	"time"

	"tkjpanel/agent/internal/config"
	"tkjpanel/agent/internal/laravel"

	_ "modernc.org/sqlite"
)

type Queue struct {
	db     *sql.DB
	appKey []byte
}

// Job adalah satu pekerjaan yang sudah didekripsi payload-nya.
type Job struct {
	ID        int64
	UUID      string
	Operation string
	Payload   map[string]any
}

func Open(cfg *config.Config) (*Queue, error) {
	db, err := sql.Open("sqlite", cfg.DatabasePath+"?_pragma=busy_timeout(5000)")
	if err != nil {
		return nil, err
	}
	if err := db.Ping(); err != nil {
		return nil, fmt.Errorf("ping db: %w", err)
	}
	return &Queue{db: db, appKey: cfg.AppKey}, nil
}

func (q *Queue) Close() error { return q.db.Close() }

// ClaimPending mengambil job status=pending (yang sudah available), lalu menandai
// running secara atomik agar tidak dieksekusi ganda.
func (q *Queue) ClaimPending(ctx context.Context, limit int) ([]Job, error) {
	rows, err := q.db.QueryContext(ctx, `
		SELECT id, uuid, operation, payload
		FROM agent_jobs
		WHERE status = 'pending'
		  AND (available_at IS NULL OR available_at <= ?)
		ORDER BY priority ASC, id ASC
		LIMIT ?`,
		time.Now().UTC().Format("2006-01-02 15:04:05"), limit,
	)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var jobs []Job
	for rows.Next() {
		var (
			id            int64
			uuid, op      string
			encryptedPayl sql.NullString
		)
		if err := rows.Scan(&id, &uuid, &op, &encryptedPayl); err != nil {
			return nil, err
		}

		payload := map[string]any{}
		if encryptedPayl.Valid && encryptedPayl.String != "" {
			decoded, err := laravel.DecryptArray(q.appKey, encryptedPayl.String)
			if err != nil {
				// Payload tak terbaca → tandai gagal, jangan eksekusi.
				_ = q.MarkFailed(ctx, id, "payload tidak dapat didekripsi: "+err.Error())
				continue
			}
			payload = decoded
		}
		jobs = append(jobs, Job{ID: id, UUID: uuid, Operation: op, Payload: payload})
	}

	// Klaim (pending → running) setelah berhasil di-decode.
	for _, j := range jobs {
		if _, err := q.db.ExecContext(ctx,
			`UPDATE agent_jobs SET status='running', started_at=?, attempts=attempts+1, updated_at=? WHERE id=? AND status='pending'`,
			nowStr(), nowStr(), j.ID,
		); err != nil {
			return nil, err
		}
	}
	return jobs, rows.Err()
}

func (q *Queue) MarkSuccess(ctx context.Context, id int64, result map[string]any) error {
	var enc sql.NullString
	if len(result) > 0 {
		raw, _ := json.Marshal(result)
		s, err := laravel.Encrypt(q.appKey, raw)
		if err != nil {
			return err
		}
		enc = sql.NullString{String: s, Valid: true}
	}
	_, err := q.db.ExecContext(ctx,
		`UPDATE agent_jobs SET status='success', result=?, finished_at=?, updated_at=? WHERE id=?`,
		enc, nowStr(), nowStr(), id,
	)
	return err
}

func (q *Queue) MarkFailed(ctx context.Context, id int64, reason string) error {
	_, err := q.db.ExecContext(ctx,
		`UPDATE agent_jobs SET status='failed', error=?, finished_at=?, updated_at=? WHERE id=?`,
		reason, nowStr(), nowStr(), id,
	)
	return err
}

func nowStr() string { return time.Now().UTC().Format("2006-01-02 15:04:05") }
