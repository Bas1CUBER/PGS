<?php

declare(strict_types=1);

// Browser-equivalent smoke test: session + XSRF cookie + real login + dashboard.
// Usage:  php smoke_test.php [base_url] [email] [password]
// Example: php smoke_test.php http://127.0.0.1:8082 admin@trcdoh.ph password

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
$email = $argv[2] ?? 'admin@trcdoh.ph';
$password = $argv[3] ?? 'password';
$jar = sys_get_temp_dir().'/pgs_smoke_cookies.txt';
@unlink($jar);

function req(string $method, string $url, array $headers = [], ?array $body = null): array
{
    global $jar;
    $ch = curl_init($url);
    $h = ['Accept: text/html'];
    foreach ($headers as $k => $v) {
        $h[] = "$k: $v";
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_HTTPHEADER => $h,
        CURLOPT_HEADER => true,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
    }
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $parts = explode("\r\n\r\n", $raw, 2);
    $headerText = $parts[0];
    $body = $parts[1] ?? '';
    preg_match_all('/^Set-Cookie:\s*([^;=]+)=([^;]*)/mi', $headerText, $m);
    $cookies = [];
    foreach ($m[1] as $i => $name) {
        $cookies[$name] = $m[2][$i];
    }

    return ['code' => $code, 'headers' => $headerText, 'body' => $body, 'cookies' => $cookies];
}

// 1) GET /login -> capture XSRF-TOKEN cookie
$r = req('GET', $base.'/login');
echo "GET /login: {$r['code']}\n";
if (($r['code'] ?? 0) !== 200) {
    exit(1);
}
$xsrf = $r['cookies']['XSRF-TOKEN'] ?? null;
echo 'XSRF cookie: '.($xsrf !== null ? strlen($xsrf).' chars' : 'MISSING')."\n";
if ($xsrf === null) {
    exit(1);
}

// 2) The X-XSRF-TOKEN header is the URL-decoded cookie value;
//    the framework decrypts it server-side (VerifyCsrfToken).
$token = rawurldecode($xsrf);
echo 'Header token: '.strlen($token)." chars\n";

// 3) POST /login with X-XSRF-TOKEN header
$r = req('POST', $base.'/login', ['X-XSRF-TOKEN' => $token, 'Content-Type' => 'application/x-www-form-urlencoded'], [
    'email' => 'admin@trcdoh.ph',
    'password' => 'password',
]);
echo "POST /login: {$r['code']}\n";
preg_match('/^Location:\s*(.*)$/mi', $r['headers'], $loc);
echo 'Redirect: '.($loc[1] ?? 'none')."\n";

// 4) GET /dashboard with the session
$r = req('GET', $base.'/dashboard');
echo "GET /dashboard: {$r['code']}\n";
echo 'Shell rendered: '.(str_contains($r['body'], 'Performance Governance System') ? 'yes' : 'no')."\n";
echo 'Inertia page data: '.(str_contains($r['body'], 'Dashboard') ? 'yes' : 'no')."\n";
