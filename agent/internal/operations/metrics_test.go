package operations

import (
	"os"
	"path/filepath"
	"testing"
)

func TestCPUPercent(t *testing.T) {
	a, _ := parseCPULine("cpu  100 0 100 700 100 0 0 0 0 0")
	b, _ := parseCPULine("cpu  150 0 150 800 100 0 0 0 0 0")
	// total +200, idle(+iowait) +100 -> 50%
	if got := cpuPercent(a, b); got != 50 {
		t.Fatalf("cpu = %v", got)
	}
	if cpuPercent(b, b) != 0 {
		t.Fatal("tanpa selisih harus 0")
	}
}

func TestParseProcStatHandlesParensInName(t *testing.T) {
	line := "1234 (php-fpm: pool (budi)) S 1 1234 1234 0 -1 4194560 100 0 0 0 250 50 0 0 20 0 1 0 100 123456 2560 18446744073709551615"
	s, ok := parseProcStat(line, 4096)
	if !ok || s.name != "php-fpm: pool (budi)" || s.ticks != 300 || s.rss != 2560*4096 {
		t.Fatalf("got %+v ok=%v", s, ok)
	}
}

func TestParseNetTable(t *testing.T) {
	tcp := `  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode
   0: 00000000:0016 00000000:0000 0A 00000000:00000000 00:00000000 00000000     0        0 1111 1 0000000000000000 100 0 0 10 0
   1: 0100007F:0CEA 00000000:0000 0A 00000000:00000000 00:00000000 00000000   106        0 2222 1 0000000000000000 100 0 0 10 0
   2: 0F02000A:0016 0202000A:D5A2 01 00000000:00000000 02:000A3D70 00000000     0        0 3333 4 0000000000000000 20 4 30 10 -1
`
	got := parseNetTable(tcp, "tcp", "0A")
	if len(got) != 2 || got[0].Port != 22 || got[0].Address != "0.0.0.0" || got[1].Port != 3306 || got[1].Address != "127.0.0.1" || got[1].Inode != "2222" {
		t.Fatalf("got %+v", got)
	}
	if a := decodeProcAddr("00000000000000000000000001000000"); a != "::1" {
		t.Fatalf("ipv6 = %s", a)
	}
}

func TestReadMemInfoAndNetFromFakeProc(t *testing.T) {
	dir := t.TempDir()
	os.MkdirAll(filepath.Join(dir, "net"), 0o755)
	os.WriteFile(filepath.Join(dir, "meminfo"), []byte("MemTotal:       8000000 kB\nMemFree:  100 kB\nMemAvailable:   6000000 kB\n"), 0o644)
	os.WriteFile(filepath.Join(dir, "net", "dev"), []byte(`Inter-|   Receive                                                |  Transmit
 face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed
    lo: 999999      10    0    0    0     0          0         0   999999      10    0    0    0     0       0          0
  eth0: 2048      10    0    0    0     0          0         0     1024      10    0    0    0     0       0          0
`), 0o644)
	old := procRoot
	procRoot = dir
	defer func() { procRoot = old }()

	if total, avail := readMemInfo(); total != 8000000 || avail != 6000000 {
		t.Fatalf("mem = %d/%d", total, avail)
	}
	if rx, tx := readNetBytes(); rx != 2048 || tx != 1024 {
		t.Fatalf("net = %d/%d (loopback harus diabaikan)", rx, tx)
	}
}
