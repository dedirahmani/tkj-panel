package operations

import (
	"context"
	"fmt"
	"net"
	"os/exec"
	"regexp"
	"strconv"
	"strings"
)

const ufwComment = "tkjpanel"

type ufwRuleSpec struct {
	Op       string // add | delete
	Action   string // allow | deny
	Port     int    // 0 = semua port
	Protocol string // tcp | udp | any
	Source   string // "" = semua
}

// ufwRule menerapkan satu aturan UFW dari nilai terstruktur (tanpa string perintah mentah).
func (r *Registry) ufwRule(ctx context.Context, payload map[string]any) (map[string]any, error) {
	spec, err := parseUfwRule(payload)
	if err != nil {
		return nil, err
	}
	if spec.Op == "add" {
		if err := spec.checkLockout(sshPorts(ctx)); err != nil {
			return nil, err
		}
	}

	out, err := exec.CommandContext(ctx, "ufw", spec.args()...).CombinedOutput()
	msg := strings.TrimSpace(string(out))
	if err != nil || strings.Contains(msg, "ERROR") {
		return nil, fmt.Errorf("ufw gagal: %v: %s", err, msg)
	}

	active := ufwActive(ctx)
	r.log.Info("ufw_rule", "op", spec.Op, "rule", spec.String(), "ufw_active", active)
	return map[string]any{
		"op": spec.Op, "rule": spec.String(), "ufw_active": active, "output": lastLines(msg, 3),
	}, nil
}

func parseUfwRule(payload map[string]any) (*ufwRuleSpec, error) {
	s := &ufwRuleSpec{}
	s.Op, _ = payload["op"].(string)
	s.Action, _ = payload["action"].(string)
	s.Protocol, _ = payload["protocol"].(string)
	src, _ := payload["source_ip"].(string)
	dir, _ := payload["direction"].(string)

	if s.Op != "add" && s.Op != "delete" {
		return nil, fmt.Errorf("op tidak valid: %q", s.Op)
	}
	if s.Action != "allow" && s.Action != "deny" {
		return nil, fmt.Errorf("action tidak valid: %q", s.Action)
	}
	if dir != "" && dir != "in" {
		return nil, fmt.Errorf("direction tidak didukung: %q", dir)
	}
	switch s.Protocol {
	case "":
		s.Protocol = "any"
	case "tcp", "udp", "any":
	default:
		return nil, fmt.Errorf("protocol tidak valid: %q", s.Protocol)
	}

	switch p := payload["port"].(type) {
	case nil:
	case float64:
		if p != float64(int(p)) || p < 1 || p > 65535 {
			return nil, fmt.Errorf("port di luar rentang: %v", p)
		}
		s.Port = int(p)
	default:
		return nil, fmt.Errorf("port tidak valid: %v", p)
	}

	if src = strings.TrimSpace(src); src != "" {
		norm, err := normalizeSource(src)
		if err != nil {
			return nil, err
		}
		s.Source = norm
	}

	// Tanpa port dan tanpa IP = "izinkan semua" (firewall jebol) atau "tolak semua"
	// (server terkunci). Keduanya tidak boleh lewat panel.
	if s.Port == 0 && s.Source == "" {
		return nil, fmt.Errorf("aturan harus punya port atau IP sumber")
	}
	return s, nil
}

// normalizeSource menerima IPv4/IPv6 atau CIDR; CIDR dinormalkan ke alamat jaringannya
// supaya perintah add dan delete selalu menghasilkan teks aturan yang sama.
func normalizeSource(src string) (string, error) {
	if strings.Contains(src, "/") {
		_, n, err := net.ParseCIDR(src)
		if err != nil {
			return "", fmt.Errorf("source_ip tidak valid: %q", src)
		}
		return n.String(), nil
	}
	ip := net.ParseIP(src)
	if ip == nil {
		return "", fmt.Errorf("source_ip tidak valid: %q", src)
	}
	return ip.String(), nil
}

// checkLockout menolak aturan "tolak dari mana saja" pada port SSH: sekali diterapkan,
// admin tidak bisa lagi masuk ke server untuk memperbaikinya.
func (s *ufwRuleSpec) checkLockout(ssh []int) error {
	if s.Action != "deny" || s.Source != "" || s.Protocol == "udp" {
		return nil
	}
	for _, p := range ssh {
		if s.Port == p {
			return fmt.Errorf("aturan ditolak: menutup port SSH %d untuk semua IP akan mengunci server", p)
		}
	}
	return nil
}

// args menyusun argumen ufw. Aturan deny memakai "prepend" (bukan "insert 1", yang gagal
// untuk IPv6 dan menaruh salinan IPv6 di bawah aturan allow) supaya blacklist selalu
// dicocokkan lebih dulu daripada allow.
func (s *ufwRuleSpec) args() []string {
	var a []string
	switch {
	case s.Op == "delete":
		a = append(a, "delete")
	case s.Action == "deny":
		a = append(a, "prepend")
	}
	a = append(a, s.Action)
	if s.Protocol != "any" {
		a = append(a, "proto", s.Protocol)
	}
	src := s.Source
	if src == "" {
		src = "any"
	}
	a = append(a, "from", src, "to", "any")
	if s.Port != 0 {
		a = append(a, "port", strconv.Itoa(s.Port))
	}
	if s.Op == "add" {
		a = append(a, "comment", ufwComment)
	}
	return a
}

func (s *ufwRuleSpec) String() string {
	src, port := s.Source, "semua port"
	if src == "" {
		src = "any"
	}
	if s.Port != 0 {
		port = strconv.Itoa(s.Port) + "/" + s.Protocol
	}
	return fmt.Sprintf("%s dari %s ke %s", s.Action, src, port)
}

var reSshdPort = regexp.MustCompile(`(?m)^port (\d+)$`)

// sshPorts membaca port SSH efektif dari `sshd -T`; default 22 bila tidak terbaca.
func sshPorts(ctx context.Context) []int {
	out, err := exec.CommandContext(ctx, "sshd", "-T").Output()
	var ports []int
	if err == nil {
		for _, m := range reSshdPort.FindAllStringSubmatch(string(out), -1) {
			if p, err := strconv.Atoi(m[1]); err == nil {
				ports = append(ports, p)
			}
		}
	}
	if len(ports) == 0 {
		ports = []int{22}
	}
	return ports
}

func ufwActive(ctx context.Context) bool {
	out, _ := exec.CommandContext(ctx, "ufw", "status").Output()
	return strings.HasPrefix(strings.TrimSpace(string(out)), "Status: active")
}
