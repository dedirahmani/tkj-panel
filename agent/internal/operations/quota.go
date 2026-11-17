package operations

import (
	"context"
	"fmt"
	"os/exec"
	"strconv"
	"strings"
)

// setDiskQuota memasang kuota disk user (blok, satuan KiB) di filesystem tempat home-nya
// berada, lewat setquota (paket "quota"). Batas lunak 95% dari batas keras supaya user
// mendapat peringatan sebelum penuh. disk_quota_mb 0 = tanpa batas. Akun SFTP turunan
// memakai UID yang sama sehingga ikut terhitung.
//
// Filesystem harus sudah mengaktifkan kuota user (ext4: opsi mount usrquota atau fitur
// "quota"; XFS: uquota). Agent tidak mengubah /etc/fstab sendiri.
func (r *Registry) setDiskQuota(ctx context.Context, payload map[string]any) (map[string]any, error) {
	username, _ := payload["linux_username"].(string)
	owner, err := lookupManagedUser(username)
	if err != nil {
		return nil, err
	}
	mb, ok := payload["disk_quota_mb"].(float64)
	if !ok || mb < 0 || mb > 1<<24 || mb != float64(int64(mb)) {
		return nil, fmt.Errorf("disk_quota_mb tidak valid: %v", payload["disk_quota_mb"])
	}
	if _, err := exec.LookPath("setquota"); err != nil {
		return nil, fmt.Errorf("paket quota belum terpasang (setquota tidak ditemukan)")
	}

	mount, err := mountPointOf(ctx, owner.Home)
	if err != nil {
		return nil, err
	}
	hard, soft := quotaBlocks(int64(mb))
	out, err := exec.CommandContext(ctx, "setquota", "-u", owner.Name,
		strconv.FormatInt(soft, 10), strconv.FormatInt(hard, 10), "0", "0", mount).CombinedOutput()
	if err != nil {
		return nil, fmt.Errorf("setquota gagal di %s (kuota user belum aktif di filesystem ini? aktifkan usrquota): %s",
			mount, strings.TrimSpace(string(out)))
	}

	used := quotaUsedKB(ctx, owner.Name, mount)
	r.log.Info("set_disk_quota", "user", owner.Name, "mount", mount, "hard_kb", hard)
	return map[string]any{
		"linux_username": owner.Name, "mount": mount, "disk_quota_mb": int64(mb),
		"hard_kb": hard, "soft_kb": soft, "used_kb": used,
	}, nil
}

func quotaBlocks(mb int64) (hard, soft int64) {
	hard = mb * 1024
	return hard, hard * 95 / 100
}

func mountPointOf(ctx context.Context, path string) (string, error) {
	out, err := exec.CommandContext(ctx, "findmnt", "-n", "-o", "TARGET", "-T", path).Output()
	mount := strings.TrimSpace(string(out))
	if err != nil || mount == "" {
		return "", fmt.Errorf("mount point %s tidak terbaca", path)
	}
	return mount, nil
}

// quotaUsedKB membaca pemakaian blok user dari `quota -w -u -f <mount>` (-1 bila gagal).
func quotaUsedKB(ctx context.Context, user, mount string) int64 {
	out, err := exec.CommandContext(ctx, "quota", "-w", "-u", "-f", mount, user).Output()
	if err != nil {
		return -1
	}
	return parseQuotaUsed(string(out))
}

// userDiskUsage menjumlahkan pemakaian blok per user dari `repquota -a -u` di semua
// filesystem berkuota. Kosong bila paket quota tidak ada atau kuota tidak aktif.
func userDiskUsage(ctx context.Context) map[string]int64 {
	if _, err := exec.LookPath("repquota"); err != nil {
		return nil
	}
	out, _ := exec.CommandContext(ctx, "repquota", "-a", "-u").Output()
	return parseRepquota(string(out))
}

func parseRepquota(out string) map[string]int64 {
	usage := map[string]int64{}
	for _, line := range strings.Split(out, "\n") {
		f := strings.Fields(line)
		if len(f) < 3 || len(f[1]) != 2 || strings.Trim(f[1], "+-") != "" || !reLinuxUser.MatchString(f[0]) {
			continue
		}
		if n, err := strconv.ParseInt(f[2], 10, 64); err == nil {
			usage[f[0]] += n
		}
	}
	return usage
}

// parseQuotaUsed: baris data "  /dev/loop0  5124*  4864  5120  6days  1  0  0" -> 5124.
func parseQuotaUsed(out string) int64 {
	for _, line := range strings.Split(out, "\n") {
		f := strings.Fields(line)
		if len(f) >= 2 && strings.HasPrefix(f[0], "/") {
			if n, err := strconv.ParseInt(strings.TrimSuffix(f[1], "*"), 10, 64); err == nil {
				return n
			}
		}
	}
	return -1
}
