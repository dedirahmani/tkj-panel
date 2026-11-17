package operations

import (
	"fmt"
	"os/user"
	"strconv"
)

// managedUser adalah user Linux milik pelanggan panel (bukan akun sistem).
type managedUser struct {
	Name string
	UID  int
	GID  int
	Home string
}

// lookupManagedUser menolak akun sistem (root, www-data, nobody, dst). Handler yang
// menjalankan sesuatu "sebagai user" (crontab, SFTP) wajib lewat sini, supaya panel
// yang dibobol tidak bisa meminta agent menulis crontab root.
func lookupManagedUser(name string) (*managedUser, error) {
	if !reLinuxUser.MatchString(name) {
		return nil, fmt.Errorf("linux_username tidak valid: %q", name)
	}
	u, err := user.Lookup(name)
	if err != nil {
		return nil, fmt.Errorf("user Linux %q tidak ditemukan", name)
	}
	uid, _ := strconv.Atoi(u.Uid)
	gid, _ := strconv.Atoi(u.Gid)
	if uid < 1000 || uid == 65534 {
		return nil, fmt.Errorf("user %q adalah akun sistem (uid %d), ditolak", name, uid)
	}
	if u.HomeDir != "/home/"+name {
		return nil, fmt.Errorf("home user %q bukan /home/%s", name, name)
	}
	return &managedUser{Name: name, UID: uid, GID: gid, Home: u.HomeDir}, nil
}
