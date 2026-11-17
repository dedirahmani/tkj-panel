package operations

import (
	"context"
	"fmt"
	"os/exec"
	"path/filepath"
	"sort"
	"strconv"
	"strings"
)

// service_control: status / start / stop / restart / reload untuk layanan di daftar putih.
// Agent sendiri (tkjpanel-agentd) dan panel tidak termasuk: me-restart diri sendiri dari
// dalam job akan memutus job itu.

type serviceDef struct {
	Name   string   // nama yang dipakai panel
	Units  []string // kandidat unit systemd (yang pertama terpasang dipakai)
	Test   []string // perintah uji konfigurasi sebelum restart/reload (opsional)
	NoStop bool     // stop ditolak (mis. ssh: server terkunci)
}

func serviceCatalog() []serviceDef {
	defs := []serviceDef{
		{Name: "nginx", Units: []string{"nginx"}, Test: []string{"nginx", "-t"}},
		{Name: "mariadb", Units: []string{"mariadb", "mysql"}},
		{Name: "ssh", Units: []string{"ssh", "sshd"}, Test: []string{"sshd", "-t"}, NoStop: true},
		{Name: "cron", Units: []string{"cron", "crond"}},
		{Name: "fail2ban", Units: []string{"fail2ban"}, Test: []string{"fail2ban-client", "-t"}},
		{Name: "cloudflared", Units: []string{"tkjpanel-cloudflared"}},
	}
	vers, _ := filepath.Glob(filepath.Join(phpEtc, "*", "fpm"))
	sort.Strings(vers)
	for _, d := range vers {
		ver := filepath.Base(filepath.Dir(d))
		if rePhpVersion.MatchString(ver) {
			defs = append(defs, serviceDef{
				Name: "php" + ver + "-fpm", Units: []string{"php" + ver + "-fpm"}, Test: []string{"php-fpm" + ver, "-t"},
			})
		}
	}
	return defs
}

type serviceState struct {
	Name, Unit, Active, Sub, Enabled, Since string
	PID                                     int
}

func (s serviceState) toMap() map[string]any {
	return map[string]any{
		"name": s.Name, "unit": s.Unit, "active": s.Active, "sub": s.Sub,
		"enabled": s.Enabled, "since": s.Since, "pid": s.PID,
	}
}

// servicesStatus membaca semua layanan katalog yang terpasang (LoadState=loaded) dengan
// satu panggilan `systemctl show`.
func servicesStatus(ctx context.Context) []serviceState {
	defs := serviceCatalog()
	var units []string
	for _, d := range defs {
		units = append(units, d.Units...)
	}
	args := append([]string{"show", "--no-pager", "-p", "Id,LoadState,ActiveState,SubState,UnitFileState,MainPID,ActiveEnterTimestamp", "--"}, withServiceSuffix(units)...)
	out, _ := exec.CommandContext(ctx, "systemctl", args...).Output()
	byUnit := parseSystemctlShow(string(out))

	var states []serviceState
	for _, d := range defs {
		for _, u := range d.Units {
			p, ok := byUnit[u+".service"]
			if !ok || p["LoadState"] != "loaded" {
				continue
			}
			pid, _ := strconv.Atoi(p["MainPID"])
			states = append(states, serviceState{
				Name: d.Name, Unit: u, Active: p["ActiveState"], Sub: p["SubState"],
				Enabled: p["UnitFileState"], Since: p["ActiveEnterTimestamp"], PID: pid,
			})
			break
		}
	}
	return states
}

func withServiceSuffix(units []string) []string {
	out := make([]string, len(units))
	for i, u := range units {
		out[i] = u + ".service"
	}
	return out
}

// parseSystemctlShow memecah keluaran `systemctl show` multi-unit (blok dipisah baris kosong).
func parseSystemctlShow(out string) map[string]map[string]string {
	res := map[string]map[string]string{}
	for _, block := range strings.Split(strings.TrimSpace(out), "\n\n") {
		props := map[string]string{}
		for _, line := range strings.Split(block, "\n") {
			if k, v, ok := strings.Cut(line, "="); ok {
				props[k] = v
			}
		}
		if id := props["Id"]; id != "" {
			res[id] = props
		}
	}
	return res
}

func (r *Registry) serviceControl(ctx context.Context, payload map[string]any) (map[string]any, error) {
	action, _ := payload["action"].(string)
	name, _ := payload["service"].(string)

	if action != "status" {
		switch action {
		case "start", "stop", "restart", "reload":
		default:
			return nil, fmt.Errorf("action tidak valid: %q", action)
		}

		var def *serviceDef
		for _, d := range serviceCatalog() {
			if d.Name == name {
				d := d
				def = &d
				break
			}
		}
		if def == nil {
			return nil, fmt.Errorf("layanan tidak ada di daftar yang boleh dikelola: %q", name)
		}
		if action == "stop" && def.NoStop {
			return nil, fmt.Errorf("%s tidak boleh dihentikan dari panel (akses ke server akan terputus); pakai restart", name)
		}
		unit := ""
		for _, st := range servicesStatus(ctx) {
			if st.Name == name {
				unit = st.Unit
			}
		}
		if unit == "" {
			return nil, fmt.Errorf("layanan %s tidak terpasang di server", name)
		}

		// Konfigurasi rusak + restart = layanan mati. Uji dulu.
		if (action == "restart" || action == "reload" || action == "start") && len(def.Test) > 0 {
			if out, err := exec.CommandContext(ctx, def.Test[0], def.Test[1:]...).CombinedOutput(); err != nil {
				return nil, fmt.Errorf("uji konfigurasi %s gagal, %s dibatalkan: %s", name, action, lastLines(string(out), 3))
			}
		}
		verb := action
		if action == "reload" {
			verb = "reload-or-restart"
		}
		if out, err := exec.CommandContext(ctx, "systemctl", verb, unit+".service").CombinedOutput(); err != nil {
			return nil, fmt.Errorf("systemctl %s %s gagal: %v: %s", verb, unit, err, strings.TrimSpace(string(out)))
		}
		r.log.Info("service_control", "service", name, "action", action)
	}

	var list []map[string]any
	for _, st := range servicesStatus(ctx) {
		list = append(list, st.toMap())
	}
	return map[string]any{"action": action, "service": name, "services": list}, nil
}
