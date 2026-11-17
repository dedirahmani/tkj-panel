package operations

import (
	"bufio"
	"context"
	"encoding/hex"
	"fmt"
	"math"
	"net"
	"os"
	"path/filepath"
	"sort"
	"strconv"
	"strings"
	"syscall"
	"time"
)

// Metrik dibaca langsung dari /proc dan statfs (tanpa ps/ss/top). CPU, jaringan, dan CPU
// per proses dihitung dari selisih dua sampel berjarak metricsWindow.
var (
	procRoot      = envOr("TKJPANEL_PROC", "/proc")
	metricsDisk   = envOr("TKJPANEL_METRICS_DISK", "/")
	metricsWindow = time.Second
)

const (
	clkTck         = 100 // USER_HZ di Linux x86/arm
	topProcesses   = 10
	maxListenPorts = 60
)

func (r *Registry) collectMetrics(ctx context.Context, payload map[string]any) (map[string]any, error) {
	cpu1, err := readCPUTimes()
	if err != nil {
		return nil, err
	}
	rx1, tx1 := readNetBytes()
	procs1 := readProcSamples()
	start := time.Now()

	select {
	case <-ctx.Done():
		return nil, ctx.Err()
	case <-time.After(metricsWindow):
	}

	cpu2, err := readCPUTimes()
	if err != nil {
		return nil, err
	}
	rx2, tx2 := readNetBytes()
	procs2 := readProcSamples()
	elapsed := time.Since(start).Seconds()

	memTotal, memAvail := readMemInfo()
	diskUsed, diskTotal, diskPct := diskUsage(metricsDisk)
	l1, l5, l15 := readLoadAvg()

	memUsed := memTotal - memAvail
	result := map[string]any{
		"captured_at":   time.Now().UTC().Format(time.RFC3339),
		"cpu_percent":   round1(cpuPercent(cpu1, cpu2)),
		"ram_percent":   round1(pct(memUsed, memTotal)),
		"ram_used_mb":   memUsed / 1024,
		"ram_total_mb":  memTotal / 1024,
		"disk_percent":  round1(diskPct),
		"disk_used_mb":  diskUsed / (1 << 20),
		"disk_total_mb": diskTotal / (1 << 20),
		"load_1":        l1,
		"load_5":        l5,
		"load_15":       l15,
		// KB/detik selama jendela sampel (bukan total sejak boot).
		"net_rx_kb": uint64(float64(sub(rx2, rx1)) / 1024 / elapsed),
		"net_tx_kb": uint64(float64(sub(tx2, tx1)) / 1024 / elapsed),
		"processes": topProcs(procs1, procs2, elapsed),
		"ports":     listeningPorts(),
	}
	// Status layanan di daftar service_control (untuk kartu "Layanan server" di panel).
	var services []map[string]any
	for _, st := range servicesStatus(ctx) {
		services = append(services, st.toMap())
	}
	result["services"] = services
	// Pemakaian disk per user (KiB) dari kuota, bila kuota aktif di server.
	if usage := userDiskUsage(ctx); len(usage) > 0 {
		result["user_disk_kb"] = usage
	}
	r.log.Info("collect_metrics", "cpu", result["cpu_percent"], "ram", result["ram_percent"], "disk", result["disk_percent"])
	return result, nil
}

// --- CPU ----------------------------------------------------------------------

type cpuTimes struct{ idle, total uint64 }

// readCPUTimes membaca baris "cpu" agregat. idle = idle + iowait; guest sudah termasuk
// di user sehingga tidak dijumlahkan dua kali.
func readCPUTimes() (cpuTimes, error) {
	f, err := os.Open(filepath.Join(procRoot, "stat"))
	if err != nil {
		return cpuTimes{}, err
	}
	defer f.Close()
	sc := bufio.NewScanner(f)
	if !sc.Scan() {
		return cpuTimes{}, fmt.Errorf("/proc/stat kosong")
	}
	return parseCPULine(sc.Text())
}

func parseCPULine(line string) (cpuTimes, error) {
	f := strings.Fields(line)
	if len(f) < 5 || f[0] != "cpu" {
		return cpuTimes{}, fmt.Errorf("format /proc/stat tidak dikenal: %q", line)
	}
	var v [8]uint64
	for i := 0; i < 8 && i+1 < len(f); i++ {
		v[i], _ = strconv.ParseUint(f[i+1], 10, 64)
	}
	var t cpuTimes
	for _, x := range v {
		t.total += x
	}
	t.idle = v[3] + v[4]
	return t, nil
}

func cpuPercent(a, b cpuTimes) float64 {
	dt := sub(b.total, a.total)
	if dt == 0 {
		return 0
	}
	return 100 * float64(dt-min(sub(b.idle, a.idle), dt)) / float64(dt)
}

// --- Memori, disk, load, jaringan --------------------------------------------

func readMemInfo() (totalKB, availKB uint64) {
	f, err := os.Open(filepath.Join(procRoot, "meminfo"))
	if err != nil {
		return 0, 0
	}
	defer f.Close()
	var free, buffers, cached uint64
	haveAvail := false
	sc := bufio.NewScanner(f)
	for sc.Scan() {
		k, rest, ok := strings.Cut(sc.Text(), ":")
		if !ok {
			continue
		}
		n, _ := strconv.ParseUint(strings.Fields(rest + " 0")[0], 10, 64)
		switch k {
		case "MemTotal":
			totalKB = n
		case "MemAvailable":
			availKB, haveAvail = n, true
		case "MemFree":
			free = n
		case "Buffers":
			buffers = n
		case "Cached":
			cached = n
		}
	}
	if !haveAvail { // kernel < 3.14
		availKB = free + buffers + cached
	}
	return totalKB, min(availKB, totalKB)
}

// diskUsage menghitung seperti df: persen = terpakai / (terpakai + tersedia untuk user).
func diskUsage(path string) (used, total uint64, percent float64) {
	var st syscall.Statfs_t
	if err := syscall.Statfs(path, &st); err != nil {
		return 0, 0, 0
	}
	bs := uint64(st.Bsize)
	total = st.Blocks * bs
	used = (st.Blocks - st.Bfree) * bs
	avail := st.Bavail * bs
	return used, total, pct(used, used+avail)
}

func readLoadAvg() (l1, l5, l15 float64) {
	b, err := os.ReadFile(filepath.Join(procRoot, "loadavg"))
	if err != nil {
		return
	}
	f := strings.Fields(string(b))
	if len(f) >= 3 {
		l1, _ = strconv.ParseFloat(f[0], 64)
		l5, _ = strconv.ParseFloat(f[1], 64)
		l15, _ = strconv.ParseFloat(f[2], 64)
	}
	return
}

// readNetBytes menjumlahkan byte semua antarmuka kecuali loopback.
func readNetBytes() (rx, tx uint64) {
	b, err := os.ReadFile(filepath.Join(procRoot, "net", "dev"))
	if err != nil {
		return
	}
	for _, line := range strings.Split(string(b), "\n") {
		name, rest, ok := strings.Cut(line, ":")
		if !ok || strings.TrimSpace(name) == "lo" {
			continue
		}
		f := strings.Fields(rest)
		if len(f) < 9 {
			continue
		}
		r, _ := strconv.ParseUint(f[0], 10, 64)
		t, _ := strconv.ParseUint(f[8], 10, 64)
		rx += r
		tx += t
	}
	return
}

// --- Proses -------------------------------------------------------------------

type procSample struct {
	name  string
	ticks uint64
	rss   uint64 // byte
}

func readProcSamples() map[int]procSample {
	out := map[int]procSample{}
	entries, err := os.ReadDir(procRoot)
	if err != nil {
		return out
	}
	page := uint64(os.Getpagesize())
	for _, e := range entries {
		pid, err := strconv.Atoi(e.Name())
		if err != nil {
			continue
		}
		b, err := os.ReadFile(filepath.Join(procRoot, e.Name(), "stat"))
		if err != nil {
			continue
		}
		if s, ok := parseProcStat(string(b), page); ok {
			out[pid] = s
		}
	}
	return out
}

// parseProcStat: "pid (comm) state ..." — comm bisa berisi spasi/kurung, jadi potong
// pada ')' terakhir. utime/stime = field 14/15, rss = field 24 (halaman).
func parseProcStat(s string, page uint64) (procSample, bool) {
	lp, rp := strings.IndexByte(s, '('), strings.LastIndexByte(s, ')')
	if lp < 0 || rp < lp {
		return procSample{}, false
	}
	f := strings.Fields(s[rp+1:])
	if len(f) < 22 {
		return procSample{}, false
	}
	ut, _ := strconv.ParseUint(f[11], 10, 64)
	st, _ := strconv.ParseUint(f[12], 10, 64)
	rss, _ := strconv.ParseInt(f[21], 10, 64)
	if rss < 0 {
		rss = 0
	}
	return procSample{name: s[lp+1 : rp], ticks: ut + st, rss: uint64(rss) * page}, true
}

// topProcs: proses dengan CPU tertinggi selama jendela sampel (100% = satu core, seperti
// top), lalu RAM sebagai pembanding. Kernel thread (rss 0) diabaikan.
func topProcs(a, b map[int]procSample, elapsed float64) []map[string]any {
	type row struct {
		pid      int
		name     string
		cpu, mem float64
	}
	var rows []row
	for pid, s2 := range b {
		if s2.rss == 0 {
			continue
		}
		var cpu float64
		if s1, ok := a[pid]; ok && s1.name == s2.name {
			cpu = float64(sub(s2.ticks, s1.ticks)) / clkTck / elapsed * 100
		}
		rows = append(rows, row{pid, s2.name, cpu, float64(s2.rss) / (1 << 20)})
	}
	sort.Slice(rows, func(i, j int) bool {
		if rows[i].cpu != rows[j].cpu {
			return rows[i].cpu > rows[j].cpu
		}
		return rows[i].mem > rows[j].mem
	})
	out := []map[string]any{}
	for i := 0; i < len(rows) && i < topProcesses; i++ {
		out = append(out, map[string]any{"pid": rows[i].pid, "name": rows[i].name, "cpu": round1(rows[i].cpu), "mem": round1(rows[i].mem)})
	}
	return out
}

// --- Port ---------------------------------------------------------------------

type listenPort struct {
	Port    int
	Proto   string
	Address string
	Inode   string
}

// listeningPorts membaca /proc/net/{tcp,tcp6,udp,udp6}: TCP berstatus LISTEN (0A) dan UDP
// yang terikat tanpa peer (07). Nama layanan dicari dari pemilik inode socket di /proc/*/fd.
func listeningPorts() []map[string]any {
	var ports []listenPort
	for _, p := range []struct{ file, proto, state string }{
		{"tcp", "tcp", "0A"}, {"tcp6", "tcp", "0A"}, {"udp", "udp", "07"}, {"udp6", "udp", "07"},
	} {
		b, err := os.ReadFile(filepath.Join(procRoot, "net", p.file))
		if err != nil {
			continue
		}
		ports = append(ports, parseNetTable(string(b), p.proto, p.state)...)
	}
	owners := socketOwners()

	seen := map[string]bool{}
	out := []map[string]any{}
	sort.Slice(ports, func(i, j int) bool {
		if ports[i].Port != ports[j].Port {
			return ports[i].Port < ports[j].Port
		}
		return ports[i].Proto < ports[j].Proto
	})
	for _, p := range ports {
		key := fmt.Sprintf("%s/%d/%s", p.Proto, p.Port, p.Address)
		if seen[key] || len(out) >= maxListenPorts {
			continue
		}
		seen[key] = true
		svc := owners[p.Inode]
		if svc == "" {
			svc = "—"
		}
		out = append(out, map[string]any{"port": p.Port, "proto": p.Proto, "address": p.Address, "service": svc})
	}
	return out
}

func parseNetTable(table, proto, wantState string) []listenPort {
	var out []listenPort
	for i, line := range strings.Split(table, "\n") {
		f := strings.Fields(line)
		if i == 0 || len(f) < 10 || f[3] != wantState {
			continue
		}
		addrHex, portHex, ok := strings.Cut(f[1], ":")
		if !ok {
			continue
		}
		port, err := strconv.ParseUint(portHex, 16, 16)
		if err != nil || port == 0 {
			continue
		}
		out = append(out, listenPort{Port: int(port), Proto: proto, Address: decodeProcAddr(addrHex), Inode: f[9]})
	}
	return out
}

// decodeProcAddr: alamat di /proc/net disimpan per kata 32-bit dalam urutan host
// (little-endian di x86/arm).
func decodeProcAddr(h string) string {
	b, err := hex.DecodeString(h)
	if err != nil || (len(b) != 4 && len(b) != 16) {
		return h
	}
	for i := 0; i+4 <= len(b); i += 4 {
		b[i], b[i+1], b[i+2], b[i+3] = b[i+3], b[i+2], b[i+1], b[i]
	}
	return net.IP(b).String()
}

// socketOwners memetakan inode socket -> nama proses (comm).
func socketOwners() map[string]string {
	owners := map[string]string{}
	entries, _ := os.ReadDir(procRoot)
	for _, e := range entries {
		if _, err := strconv.Atoi(e.Name()); err != nil {
			continue
		}
		dir := filepath.Join(procRoot, e.Name())
		fds, err := os.ReadDir(filepath.Join(dir, "fd"))
		if err != nil {
			continue
		}
		var comm string
		for _, fd := range fds {
			link, err := os.Readlink(filepath.Join(dir, "fd", fd.Name()))
			if err != nil || !strings.HasPrefix(link, "socket:[") {
				continue
			}
			if comm == "" {
				b, _ := os.ReadFile(filepath.Join(dir, "comm"))
				comm = strings.TrimSpace(string(b))
			}
			inode := strings.TrimSuffix(strings.TrimPrefix(link, "socket:["), "]")
			if _, ok := owners[inode]; !ok {
				owners[inode] = comm
			}
		}
	}
	return owners
}

// --- util ---------------------------------------------------------------------

func sub(a, b uint64) uint64 {
	if a < b {
		return 0 // counter reset/wrap
	}
	return a - b
}

func pct(part, whole uint64) float64 {
	if whole == 0 {
		return 0
	}
	return 100 * float64(part) / float64(whole)
}

func round1(f float64) float64 { return math.Round(f*10) / 10 }
