package operations

import (
	"bytes"
	"context"
	"errors"
	"fmt"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"strings"
	"time"
)

// git_deploy menjalankan git dan perintah build SEBAGAI user situs (runuser), di folder
// situs /home/<user>/<domain>. Repo privat lewat SSH memakai deploy key per situs yang
// dibuat agent (~/.ssh/tkjpanel_deploy_<domain>); kunci publiknya dikembalikan ke panel.
var (
	reGitSSHURL   = regexp.MustCompile(`^git@[A-Za-z0-9.-]{1,253}:[A-Za-z0-9._/~-]{1,200}\.git$`)
	reGitBranch   = regexp.MustCompile(`^[A-Za-z0-9][A-Za-z0-9._/-]{0,79}$`)
	reURLUserinfo = regexp.MustCompile(`(https?://)[^/@\s]+@`)
	deployTimeout = 20 * time.Minute
)

const deployOutputTail = 60 // baris output yang dikirim ke panel

type deploySpec struct {
	Domain   string
	Owner    *managedUser
	SiteRoot string
	RepoURL  string
	Branch   string
	Build    string
	IsSSH    bool
	KeyPath  string
}

func parseDeploySpec(payload map[string]any) (*deploySpec, error) {
	s := &deploySpec{}
	s.Domain, _ = payload["domain"].(string)
	if !reDomain.MatchString(s.Domain) || strings.Contains(s.Domain, "*") || strings.Contains(s.Domain, "..") {
		return nil, fmt.Errorf("domain tidak valid: %q", s.Domain)
	}
	username, _ := payload["linux_username"].(string)
	owner, err := lookupManagedUser(username)
	if err != nil {
		return nil, err
	}
	s.Owner = owner
	docroot, _ := payload["docroot"].(string)
	if s.SiteRoot, err = resolveSiteRoot(owner, s.Domain, docroot); err != nil {
		return nil, err
	}
	s.KeyPath = filepath.Join(owner.Home, ".ssh", "tkjpanel_deploy_"+s.Domain)

	s.RepoURL, _ = payload["repo_url"].(string)
	if s.IsSSH, err = validateRepoURL(s.RepoURL); err != nil {
		return nil, err
	}
	s.Branch, _ = payload["branch"].(string)
	if !validBranch(s.Branch) {
		return nil, fmt.Errorf("branch tidak valid: %q", s.Branch)
	}
	s.Build, _ = payload["build_command"].(string)
	s.Build = strings.TrimSpace(s.Build)
	if strings.ContainsRune(s.Build, 0) || len(s.Build) > 1000 {
		return nil, fmt.Errorf("build_command tidak valid")
	}
	return s, nil
}

// validateRepoURL hanya menerima https://... atau git@host:path.git. Transport lain
// (ext::, file://, --upload-pack=...) ditolak di sini DAN diblokir lewat GIT_ALLOW_PROTOCOL.
func validateRepoURL(raw string) (isSSH bool, err error) {
	if reGitSSHURL.MatchString(raw) {
		return true, nil
	}
	u, perr := url.Parse(raw)
	if perr != nil || u.Scheme != "https" || u.Host == "" || strings.ContainsAny(raw, " \t\r\n\\") || strings.HasPrefix(u.Path, "/-") {
		return false, fmt.Errorf("repo_url harus https://… atau git@host:repo.git: %q", redactURL(raw))
	}
	return false, nil
}

func validBranch(b string) bool {
	return reGitBranch.MatchString(b) && !strings.Contains(b, "..") && !strings.Contains(b, "//") &&
		!strings.HasSuffix(b, "/") && !strings.HasSuffix(b, ".lock")
}

func redactURL(s string) string { return reURLUserinfo.ReplaceAllString(s, "${1}***@") }

// gitDeploy: action "deploy" (default) atau "keygen" (buat deploy key SSH saja).
func (r *Registry) gitDeploy(ctx context.Context, payload map[string]any) (map[string]any, error) {
	s, err := parseDeploySpec(payload)
	if err != nil {
		return nil, err
	}
	ctx, cancel := context.WithTimeout(ctx, deployTimeout)
	defer cancel()

	if action, _ := payload["action"].(string); action == "keygen" {
		pub, err := s.ensureDeployKey(ctx)
		if err != nil {
			return nil, err
		}
		r.log.Info("git_deploy keygen", "domain", s.Domain)
		return map[string]any{"action": "keygen", "public_key": pub}, nil
	}

	log := &deployLog{}
	res, err := s.deploy(ctx, log)
	if err != nil {
		r.log.Error("git_deploy gagal", "domain", s.Domain, "err", err)
		return nil, fmt.Errorf("%v\n--- output ---\n%s", err, log.tail(25))
	}
	res["output"] = log.tail(deployOutputTail)
	r.log.Info("git_deploy", "domain", s.Domain, "commit", res["commit"])
	return res, nil
}

func (s *deploySpec) deploy(ctx context.Context, log *deployLog) (map[string]any, error) {
	pub := ""
	if s.IsSSH {
		var err error
		if pub, err = s.ensureDeployKey(ctx); err != nil {
			return nil, err
		}
	}

	// Folder situs milik user; tolak symlink supaya git tidak diarahkan ke luar home.
	if st, err := os.Lstat(s.SiteRoot); os.IsNotExist(err) {
		if err := mkdirOwned(s.SiteRoot, s.Owner, 0o755); err != nil {
			return nil, err
		}
	} else if err != nil {
		return nil, err
	} else if !st.IsDir() || st.Mode()&os.ModeSymlink != 0 {
		return nil, fmt.Errorf("%s bukan folder biasa", s.SiteRoot)
	}

	if _, err := os.Lstat(filepath.Join(s.SiteRoot, ".git")); os.IsNotExist(err) {
		if err := s.git(ctx, log, "init", "-q"); err != nil {
			return nil, err
		}
	}
	// Commit yang sedang terpasang: tujuan rollback bila build gagal (kosong = deploy pertama).
	prev, _ := s.gitOutput(ctx, "rev-parse", "--verify", "-q", "HEAD^{commit}")
	_ = s.git(ctx, log, "remote", "remove", "origin") // boleh gagal bila belum ada
	steps := [][]string{
		{"remote", "add", "origin", s.RepoURL},
		{"fetch", "--depth", "1", "--no-tags", "origin", "+refs/heads/" + s.Branch + ":refs/remotes/origin/" + s.Branch},
		// -f menimpa berkas tak terlacak yang menghalangi (mis. index.html bawaan panel);
		// berkas tak terlacak lain (.env, uploads) tidak disentuh.
		{"checkout", "-q", "-f", "-B", s.Branch, "refs/remotes/origin/" + s.Branch},
	}
	for _, st := range steps {
		if err := s.git(ctx, log, st...); err != nil {
			if s.IsSSH && st[0] == "fetch" {
				return nil, fmt.Errorf("%w (pastikan deploy key ini sudah ditambahkan ke repo: %s)", err, pub)
			}
			return nil, err
		}
	}
	if _, err := os.Stat(filepath.Join(s.SiteRoot, ".gitmodules")); err == nil {
		if err := s.git(ctx, log, "submodule", "update", "--init", "--recursive", "--depth", "1"); err != nil {
			return nil, err
		}
	}

	commit, _ := s.gitOutput(ctx, "rev-parse", "HEAD")
	subject, _ := s.gitOutput(ctx, "log", "-1", "--format=%s")

	if s.Build != "" {
		log.add("$ " + s.Build)
		out, err := s.runAsUser(ctx, "/bin/sh", "-c", s.Build)
		log.add(out)
		if err != nil {
			// Kode baru tanpa build yang berhasil (mis. vendor/ belum lengkap) bisa membuat situs
			// rusak: kembalikan berkas ke commit sebelumnya. Efek samping build (migrasi DB)
			// tidak bisa dibatalkan.
			if prev != "" && prev != commit {
				if rerr := s.git(ctx, log, "checkout", "-q", "-f", "-B", s.Branch, prev); rerr == nil {
					return nil, fmt.Errorf("perintah build gagal: %w; berkas dikembalikan ke commit %.8s", err, prev)
				}
			}
			return nil, fmt.Errorf("perintah build gagal: %w", err)
		}
	}

	res := map[string]any{
		"action": "deploy", "branch": s.Branch, "commit": commit, "commit_message": subject,
		"deployed_at": time.Now().UTC().Format(time.RFC3339),
	}
	if pub != "" {
		res["public_key"] = pub
	}
	return res, nil
}

// ensureDeployKey membuat kunci ed25519 milik user bila belum ada, lalu mengembalikan
// kunci publiknya.
func (s *deploySpec) ensureDeployKey(ctx context.Context) (string, error) {
	sshDir := filepath.Dir(s.KeyPath)
	if err := ensureOwnedPlainDir(sshDir, s.Owner); err != nil {
		return "", err
	}
	if _, err := os.Lstat(s.KeyPath); os.IsNotExist(err) {
		out, err := s.runAsUser(ctx, "ssh-keygen", "-q", "-t", "ed25519", "-N", "", "-C", "tkjpanel-deploy@"+s.Domain, "-f", s.KeyPath)
		if err != nil {
			return "", fmt.Errorf("ssh-keygen gagal: %v: %s", err, lastLines(out, 2))
		}
	}
	pub, err := os.ReadFile(s.KeyPath + ".pub")
	if err != nil {
		return "", err
	}
	return strings.TrimSpace(string(pub)), nil
}

func (s *deploySpec) git(ctx context.Context, log *deployLog, args ...string) error {
	log.add("$ git " + redactURL(strings.Join(args, " ")))
	out, err := s.runAsUser(ctx, "git", args...)
	log.add(out)
	if err != nil {
		return fmt.Errorf("git %s gagal: %w", args[0], err)
	}
	return nil
}

func (s *deploySpec) gitOutput(ctx context.Context, args ...string) (string, error) {
	out, err := s.runAsUser(ctx, "git", args...)
	return strings.TrimSpace(out), err
}

// runAsUser menjalankan perintah sebagai user situs di folder situs dengan lingkungan
// minimal. Git hanya boleh memakai https/ssh dan tidak pernah meminta kredensial interaktif.
func (s *deploySpec) runAsUser(ctx context.Context, name string, args ...string) (string, error) {
	cmd := exec.CommandContext(ctx, "runuser", append([]string{"-u", s.Owner.Name, "--", name}, args...)...)
	cmd.Dir = s.SiteRoot
	if _, err := os.Stat(s.SiteRoot); err != nil {
		cmd.Dir = s.Owner.Home
	}
	cmd.Env = []string{
		"HOME=" + s.Owner.Home, "USER=" + s.Owner.Name, "LOGNAME=" + s.Owner.Name,
		"PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin", "LANG=C.UTF-8",
		"GIT_TERMINAL_PROMPT=0", "GIT_ALLOW_PROTOCOL=https:ssh",
		"GIT_SSH_COMMAND=ssh -i " + s.KeyPath + " -o IdentitiesOnly=yes -o BatchMode=yes" +
			" -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=" + filepath.Join(s.Owner.Home, ".ssh", "known_hosts"),
	}
	var buf bytes.Buffer
	cmd.Stdout, cmd.Stderr = &buf, &buf
	err := cmd.Run()
	if errors.Is(ctx.Err(), context.DeadlineExceeded) {
		err = fmt.Errorf("melewati batas waktu %s", deployTimeout)
	}
	return redactURL(buf.String()), err
}

// deployLog mengumpulkan output (sudah disensor) untuk ditampilkan di panel.
type deployLog struct{ lines []string }

func (l *deployLog) add(s string) {
	s = strings.TrimRight(s, "\n")
	if s != "" {
		l.lines = append(l.lines, strings.Split(s, "\n")...)
	}
}

func (l *deployLog) tail(n int) string {
	lines := l.lines
	if len(lines) > n {
		lines = append([]string{fmt.Sprintf("… (%d baris sebelumnya dipotong)", len(lines)-n)}, lines[len(lines)-n:]...)
	}
	return strings.Join(lines, "\n")
}
