<?php
/**
 * Fix: pvesh() POST empty body issue
 */
$f = "/var/www/pve-panel/app/Services/ProxmoxService.php";
$code = file_get_contents($f);

// Fix 1: For POST/PUT/PATCH, always set CURLOPT_POSTFIELDS
$old = "if (!empty(\$data) && in_array(strtoupper(\$method), ['POST', 'PUT', 'PATCH'])) {
                curl_setopt(\$ch, CURLOPT_POSTFIELDS, json_encode(\$data));
            }";
$new = "if (in_array(strtoupper(\$method), ['POST', 'PUT', 'PATCH'])) {
                curl_setopt(\$ch, CURLOPT_POST, true);
                curl_setopt(\$ch, CURLOPT_POSTFIELDS, json_encode(\$data));
                unset(\$ch_headers['CURLOPT_CUSTOMREQUEST']); // don't use custom request for POST
            }";

$code = str_replace($old, $new, $code, $cnt);
echo "Fix 1: replaced $cnt occurrence(s)\n";

// Fix 2: Remove CUSTOMREQUEST for POST methods (they conflict with CURLOPT_POST)
$old2 = "CURLOPT_CUSTOMREQUEST  => strtoupper(\$method),";
$new2 = "// Use CURLOPT_POST for POST/PUT; CUSTOMREQUEST only for GET/DELETE/HEAD
            CURLOPT_CUSTOMREQUEST  => in_array(strtoupper(\$method), ['POST','PUT','PATCH']) ? null : strtoupper(\$method),";

$code = str_replace($old2, $new2, $code, $cnt2);
echo "Fix 2: replaced $cnt2 occurrence(s)\n";

// Fix 3: Actually let's use a cleaner approach - just set POSTFIELDS always for write methods
// Replace the full curl_setopt_array to be cleaner

file_put_contents($f, $code);
echo "File saved\n\n";

// Verify
echo "Verification:\n";
$lines = explode("\n", $code);
for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], 'CURLOPT_POSTFIELDS') !== false) {
        echo "  Line " . ($i+1) . ": " . trim($lines[$i]) . "\n";
    }
}

// Clear cache and restart
shell_exec("cd /var/www/pve-panel && php artisan optimize:clear 2>&1");
shell_exec("systemctl reload php8.2-fpm 2>&1");
echo "\nCache cleared, PHP-FPM reloaded\n";
