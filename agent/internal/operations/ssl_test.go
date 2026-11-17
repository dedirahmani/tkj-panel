package operations

import (
	"strings"
	"testing"
)

const sampleVhost = `# Dikelola TKJPanel — jangan sunting manual
server {
    listen 80;
    listen [::]:80;
    server_name uji.test www.uji.test;

    root /home/uji/uji.test/public;
}
`

func TestInjectSSLInsertsAfterServerName(t *testing.T) {
	out := injectSSL(sampleVhost, sslLines("uji.test", false))
	lines := strings.Split(out, "\n")
	for i, l := range lines {
		if strings.HasPrefix(strings.TrimSpace(l), "server_name ") {
			if !strings.Contains(lines[i+1], "listen 443 ssl;") {
				t.Fatalf("baris SSL tidak tepat setelah server_name:\n%s", out)
			}
			return
		}
	}
	t.Fatal("server_name tidak ditemukan")
}

func TestInjectSSLIsIdempotentAndTogglesForceHTTPS(t *testing.T) {
	once := injectSSL(sampleVhost, sslLines("uji.test", true))
	twice := injectSSL(once, sslLines("uji.test", true))
	if once != twice {
		t.Fatalf("injectSSL tidak idempoten:\n%s\n---\n%s", once, twice)
	}
	if strings.Count(twice, "listen 443 ssl;") != 1 {
		t.Fatal("listen 443 terduplikasi")
	}
	if !strings.Contains(twice, "return 301 https://") {
		t.Fatal("force HTTPS tidak ada")
	}

	off := injectSSL(twice, sslLines("uji.test", false))
	if strings.Contains(off, "return 301 https://") {
		t.Fatal("force HTTPS seharusnya hilang")
	}
}

func TestInjectSSLKeepsNonSSLLines(t *testing.T) {
	out := injectSSL(injectSSL(sampleVhost, sslLines("uji.test", true)), nil)
	if strings.TrimSpace(out) != strings.TrimSpace(sampleVhost) {
		t.Fatalf("baris non-SSL berubah:\n%s", out)
	}
}
