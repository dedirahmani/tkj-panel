package operations

import (
	"strings"
	"testing"
)

func TestSystemdEscapePath(t *testing.T) {
	// Nilai acuan dari `systemd-escape --path`.
	cases := map[string]string{
		"/srv/tkjpanel-sftp/budi-ftp/files": `srv-tkjpanel\x2dsftp-budi\x2dftp-files`,
		"/srv/a_b/c.d/":                     `srv-a_b-c.d`,
		"/":                                 `-`,
	}
	for in, want := range cases {
		if got := systemdEscapePath(in); got != want {
			t.Errorf("%s -> %s, want %s", in, got, want)
		}
	}
}

func TestSftpTargetStaysInsideOwnerHome(t *testing.T) {
	owner := &managedUser{Name: "budi", Home: "/home/budi"}
	got, err := sftpTarget(owner, "/home/budi/web/uploads/")
	if err != nil || got != "/home/budi/web/uploads" {
		t.Fatalf("got %q, %v", got, err)
	}
	for _, bad := range []string{
		"/home/budi", "/home/budi/", "/home/budi/../ani", "/home/budiX/x",
		"/home/ani/x", "/etc", "/home/budi/a b", "/home/budi/a//b", "/home/budi/.ssh",
	} {
		if _, err := sftpTarget(owner, bad); err == nil {
			t.Errorf("%q seharusnya ditolak", bad)
		}
	}
}

func TestSshdSftpConfigChrootsGroup(t *testing.T) {
	c := sshdSftpConfig()
	for _, want := range []string{"Match Group tkjsftp", "ChrootDirectory " + sftpRoot + "/%u", "ForceCommand internal-sftp -d /files", "AllowTcpForwarding no"} {
		if !strings.Contains(c, want) {
			t.Errorf("konfigurasi tidak memuat %q", want)
		}
	}
}

func TestValidateLinuxPassword(t *testing.T) {
	if validateLinuxPassword("Rahasia123") != nil {
		t.Fatal("password valid ditolak")
	}
	for _, bad := range []string{"pendek", "abcdefgh\nroot:x", strings.Repeat("a", 129)} {
		if validateLinuxPassword(bad) == nil {
			t.Errorf("%q seharusnya ditolak", bad)
		}
	}
}
