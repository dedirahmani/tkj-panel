package operations

import (
	"os"
	"path/filepath"
	"sort"
	"testing"
)

func TestQuotaBlocksAndParse(t *testing.T) {
	if hard, soft := quotaBlocks(2048); hard != 2097152 || soft != 1992294 {
		t.Fatalf("hard=%d soft=%d", hard, soft)
	}
	out := "Disk quotas for user budi (uid 1001):\n     Filesystem  blocks   quota   limit   grace   files   quota   limit   grace\n      /dev/loop0    5124*   4864    5120   6days       3       0       0\n"
	if got := parseQuotaUsed(out); got != 5124 {
		t.Fatalf("used = %d", got)
	}
	if parseQuotaUsed("quota: error") != -1 {
		t.Fatal("output rusak harus -1")
	}
}

func TestParseRepquota(t *testing.T) {
	out := `*** Report for user quotas on device /dev/loop0
Block grace time: 7days; Inode grace time: 7days
                        Block limits                File limits
User            used    soft    hard  grace    used  soft  hard  grace
----------------------------------------------------------------------
root      --      20       0       0              2     0     0
tkjuji    +-    5124    4864    5120  6days       3     0     0
#1005     --       8       0       0              1     0     0

*** Report for user quotas on device /dev/sda1
tkjuji    --     100       0       0              1     0     0
`
	got := parseRepquota(out)
	if got["tkjuji"] != 5224 || got["root"] != 20 || len(got) != 2 {
		t.Fatalf("got %v", got)
	}
}

func TestUserDomainsOnlyClaimsOwnedVhosts(t *testing.T) {
	dir := t.TempDir()
	oldNginx, oldPhp := nginxAvailable, phpEtc
	nginxAvailable, phpEtc = filepath.Join(dir, "nginx"), filepath.Join(dir, "php")
	defer func() { nginxAvailable, phpEtc = oldNginx, oldPhp }()
	os.MkdirAll(nginxAvailable, 0o755)
	os.MkdirAll(filepath.Join(phpEtc, "8.4", "fpm", "pool.d"), 0o755)

	vhost := func(domain, root string) {
		os.WriteFile(filepath.Join(nginxAvailable, domain+".conf"),
			[]byte("# Dikelola TKJPanel — jangan sunting manual\nserver {\n    root "+root+";\n}\n"), 0o644)
	}
	vhost("budi.test", "/home/budi/budi.test/public")
	vhost("ani.test", "/home/ani/ani.test/public")
	vhost("budiman.test", "/home/budiman/budiman.test/public") // awalan nama sama, bukan milik budi
	os.WriteFile(filepath.Join(nginxAvailable, "redirect.test.conf"), []byte("# Dikelola TKJPanel\nserver {\n    return 301 https://x;\n}\n"), 0o644)
	os.WriteFile(filepath.Join(phpEtc, "8.4", "fpm", "pool.d", "tkjpanel-yatim.test.conf"), []byte("[yatim.test]\nuser = budi\ngroup = budi\n"), 0o644)

	owner := &managedUser{Name: "budi", Home: "/home/budi"}
	var warnings []string
	got := userDomains(owner, []string{"redirect.test", "ani.test", "../etc"}, func(f string, a ...any) { warnings = append(warnings, f) })
	sort.Strings(got)
	want := []string{"budi.test", "redirect.test", "yatim.test"}
	if len(got) != len(want) || got[0] != want[0] || got[1] != want[1] || got[2] != want[2] {
		t.Fatalf("got %v want %v", got, want)
	}
	if len(warnings) != 2 { // ani.test (milik orang lain) dan ../etc
		t.Fatalf("warnings = %v", warnings)
	}
}
