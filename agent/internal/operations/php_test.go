package operations

import (
	"strings"
	"testing"
)

func TestRenderPool(t *testing.T) {
	s := &phpPoolSpec{
		Domain: "budi.sch.id", Version: "8.4", OpenBasedir: "/home/budi/:/tmp/",
		Owner:      &managedUser{Name: "budi", Home: "/home/budi"},
		Directives: map[string]string{"memory_limit": "512M", "display_errors": "On"},
	}
	out := renderPool(s)
	for _, want := range []string{
		"[budi.sch.id]", "user = budi", "listen = /run/php/php8.4-fpm-budi.sch.id.sock",
		"listen.owner = www-data", "php_admin_value[open_basedir] = /home/budi/:/tmp/",
		"php_admin_value[session.save_path] = /home/budi/.tmp",
		"php_admin_flag[display_errors] = on", "php_admin_value[memory_limit] = 512M",
	} {
		if !strings.Contains(out, want) {
			t.Errorf("pool tidak memuat %q:\n%s", want, out)
		}
	}
}

func TestValidateOpenBasedir(t *testing.T) {
	owner := &managedUser{Name: "budi", Home: "/home/budi"}
	if got, err := validateOpenBasedir("", owner); err != nil || got != "/home/budi/:/tmp/" {
		t.Fatalf("default = %q, %v", got, err)
	}
	if _, err := validateOpenBasedir("/home/budi/:/tmp/", owner); err != nil {
		t.Fatal(err)
	}
	for _, bad := range []string{"/", "/home/budi/:/etc/", "/home/budix/", "/home/budi/../ani", "relatif", "/home/budi/\nphp_admin_value[x]=1"} {
		if _, err := validateOpenBasedir(bad, owner); err == nil {
			t.Errorf("%q seharusnya ditolak", bad)
		}
	}
}

func TestPhpDirectiveWhitelist(t *testing.T) {
	ok := map[string]string{"memory_limit": "-1", "upload_max_filesize": "64M", "max_input_time": "-1", "display_errors": "Off"}
	for k, v := range ok {
		if !phpIniWhitelist[k].MatchString(v) {
			t.Errorf("%s=%s seharusnya valid", k, v)
		}
	}
	bad := map[string]string{"memory_limit": "1G\nphp_admin_value[open_basedir]=/", "display_errors": "stderr", "max_execution_time": "1e9"}
	for k, v := range bad {
		if phpIniWhitelist[k].MatchString(v) {
			t.Errorf("%s=%q seharusnya ditolak", k, v)
		}
	}
	if _, ok := phpIniWhitelist["disable_functions"]; ok {
		t.Error("disable_functions tidak boleh bisa diatur panel")
	}
}
