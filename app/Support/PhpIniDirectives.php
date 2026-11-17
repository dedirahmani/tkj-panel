<?php

namespace App\Support;

/**
 * DAFTAR-PUTIH direktif php.ini yang boleh diedit per situs via UI (PRD 6.2).
 * Hanya direktif "aman" yang diekspos; nilai divalidasi ketat sebelum diteruskan
 * ke agent (write_php_ini). Direktif di luar daftar ini diabaikan.
 */
class PhpIniDirectives
{
    /**
     * key => [default, rules (array), type]
     * type: 'size' (mis. 256M), 'int', 'bool' (On/Off)
     *
     * Rules disimpan sebagai array (bukan string ber-'|') karena pola regex
     * memuat karakter '|' yang akan rusak bila di-explode.
     *
     * @return array<string, array{default: string, rules: array<int, string>, type: string}>
     */
    public static function definitions(): array
    {
        return [
            'memory_limit' => ['default' => '256M', 'rules' => ['regex:/^(-1|\d+[KMG]?)$/i'], 'type' => 'size'],
            'upload_max_filesize' => ['default' => '64M', 'rules' => ['regex:/^\d+[KMG]?$/i'], 'type' => 'size'],
            'post_max_size' => ['default' => '64M', 'rules' => ['regex:/^\d+[KMG]?$/i'], 'type' => 'size'],
            'max_execution_time' => ['default' => '60', 'rules' => ['integer', 'min:0', 'max:3600'], 'type' => 'int'],
            'max_input_time' => ['default' => '60', 'rules' => ['integer', 'min:-1', 'max:3600'], 'type' => 'int'],
            'max_input_vars' => ['default' => '1000', 'rules' => ['integer', 'min:1', 'max:100000'], 'type' => 'int'],
            'display_errors' => ['default' => 'Off', 'rules' => ['in:On,Off'], 'type' => 'bool'],
        ];
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /** @return array<string, string> key => nilai default */
    public static function defaults(): array
    {
        return array_map(fn ($d) => $d['default'], self::definitions());
    }

    /**
     * Aturan validasi Laravel untuk input php_ini[<key>].
     *
     * @return array<string, array<int, string>>
     */
    public static function validationRules(): array
    {
        $rules = [];
        foreach (self::definitions() as $key => $def) {
            $rules["php_ini.$key"] = ['nullable', ...$def['rules']];
        }

        return $rules;
    }

    /**
     * Bersihkan input: hanya key whitelist, buang nilai kosong.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function sanitize(array $input): array
    {
        $out = [];
        foreach (self::keys() as $key) {
            $val = $input[$key] ?? null;
            if ($val !== null && $val !== '') {
                $out[$key] = (string) $val;
            }
        }

        return $out;
    }
}
