package terminal

import (
	"fmt"
	"os"
	"os/exec"
	"os/user"
	"strconv"
	"syscall"

	"golang.org/x/sys/unix"
)

// openPTY membuka pasangan master/slave lewat /dev/ptmx (tanpa dependensi luar).
func openPTY() (master, slave *os.File, err error) {
	master, err = os.OpenFile("/dev/ptmx", os.O_RDWR|syscall.O_NOCTTY|syscall.O_CLOEXEC, 0)
	if err != nil {
		return nil, nil, err
	}
	var n int
	err = withFd(master, func(fd int) error {
		if err := unix.IoctlSetPointerInt(fd, unix.TIOCSPTLCK, 0); err != nil {
			return fmt.Errorf("unlockpt: %w", err)
		}
		var gerr error
		n, gerr = unix.IoctlGetInt(fd, unix.TIOCGPTN)
		return gerr
	})
	if err != nil {
		master.Close()
		return nil, nil, fmt.Errorf("ptsname: %w", err)
	}
	slave, err = os.OpenFile("/dev/pts/"+strconv.Itoa(n), os.O_RDWR|syscall.O_NOCTTY, 0)
	if err != nil {
		master.Close()
		return nil, nil, err
	}
	return master, slave, nil
}

func setWinsize(f *os.File, cols, rows uint16) error {
	if cols == 0 || rows == 0 || cols > 1000 || rows > 1000 {
		return fmt.Errorf("ukuran terminal tidak valid")
	}
	return withFd(f, func(fd int) error {
		return unix.IoctlSetWinsize(fd, unix.TIOCSWINSZ, &unix.Winsize{Col: cols, Row: rows})
	})
}

// withFd memberi akses fd mentah TANPA f.Fd(): Fd() mengubah fd menjadi blocking sehingga
// Close() tidak bisa lagi menginterupsi Read() yang sedang menunggu di goroutine lain.
func withFd(f *os.File, fn func(fd int) error) error {
	rc, err := f.SyscallConn()
	if err != nil {
		return err
	}
	var ferr error
	if err := rc.Control(func(fd uintptr) { ferr = fn(int(fd)) }); err != nil {
		return err
	}
	return ferr
}

// shellUser adalah akun yang boleh dibuka terminalnya: bukan akun sistem.
type shellUser struct {
	Name     string
	UID, GID uint32
	Groups   []uint32
	Home     string
}

func lookupShellUser(name string) (*shellUser, error) {
	u, err := user.Lookup(name)
	if err != nil {
		return nil, fmt.Errorf("user %q tidak ditemukan", name)
	}
	uid, _ := strconv.Atoi(u.Uid)
	gid, _ := strconv.Atoi(u.Gid)
	if uid < 1000 || uid == 65534 || u.HomeDir != "/home/"+name {
		return nil, fmt.Errorf("user %q bukan akun pelanggan", name)
	}
	s := &shellUser{Name: name, UID: uint32(uid), GID: uint32(gid), Home: u.HomeDir}
	gids, _ := u.GroupIds()
	for _, g := range gids {
		if n, err := strconv.Atoi(g); err == nil {
			s.Groups = append(s.Groups, uint32(n))
		}
	}
	return s, nil
}

// startShell menjalankan bash login sebagai user di PTY baru. Credential diset langsung
// (tanpa su/PAM): shell login user boleh nologin, terminal panel tetap memakai bash.
// Karena agent berjalan dengan NoNewPrivileges, sudo/su dari dalam terminal tidak bisa
// menaikkan hak.
func startShell(u *shellUser, cols, rows uint16) (*exec.Cmd, *os.File, error) {
	master, slave, err := openPTY()
	if err != nil {
		return nil, nil, err
	}
	defer slave.Close()
	_ = os.Chown(slave.Name(), int(u.UID), -1)
	_ = setWinsize(master, cols, rows)

	cmd := exec.Command("/bin/bash", "-l")
	cmd.Dir = u.Home
	cmd.Env = []string{
		"HOME=" + u.Home, "USER=" + u.Name, "LOGNAME=" + u.Name, "SHELL=/bin/bash",
		"TERM=xterm-256color", "LANG=C.UTF-8",
		"PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin",
	}
	cmd.Stdin, cmd.Stdout, cmd.Stderr = slave, slave, slave
	cmd.SysProcAttr = &syscall.SysProcAttr{Setsid: true, Setctty: true, Ctty: 0}
	if os.Geteuid() == 0 {
		cmd.SysProcAttr.Credential = &syscall.Credential{Uid: u.UID, Gid: u.GID, Groups: u.Groups}
	}
	if err := cmd.Start(); err != nil {
		master.Close()
		return nil, nil, err
	}
	return cmd, master, nil
}
