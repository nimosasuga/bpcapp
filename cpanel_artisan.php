<?php
/**
 * cPanel Web Artisan Runner
 * Gunakan file ini jika hosting cPanel tidak menyediakan akses Terminal / SSH.
 * Akses: https://bpcapp.exprosalab.com/cpanel_artisan.php?token=bpcapp2026_deploy_secret&cmd=migrate
 */

$secretToken = 'bpcapp2026_deploy_secret';

if (!isset($_GET['token']) || $_GET['token'] !== $secretToken) {
    http_response_code(403);
    die("Akses Ditolak. Token keamanan salah atau tidak disertakan.");
}

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Contracts\Console\Kernel;

$kernel = $app->make(Kernel::class);

$cmd = $_GET['cmd'] ?? 'help';

echo "<h2>PartSales Hub - cPanel Artisan Runner</h2>";
echo "<pre style='background:#1e1e1e;color:#00ff66;padding:15px;border-radius:6px;font-family:monospace;'>";

try {
    switch ($cmd) {
        case 'migrate':
            echo "Menjalankan: php artisan migrate --force\n\n";
            $kernel->call('migrate', ['--force' => true]);
            break;

        case 'seed':
            echo "Menjalankan: php artisan db:seed --force\n\n";
            $kernel->call('db:seed', ['--force' => true]);
            break;

        case 'storage_link':
            echo "Menjalankan: php artisan storage:link\n\n";
            $kernel->call('storage:link');
            break;

        case 'cache_clear':
            echo "Membersihkan Cache...\n\n";
            $kernel->call('config:clear');
            $kernel->call('cache:clear');
            $kernel->call('view:clear');
            $kernel->call('route:clear');
            break;

        case 'optimize':
            echo "Menjalankan: php artisan optimize\n\n";
            $kernel->call('optimize');
            break;

        default:
            echo "Perintah yang tersedia:\n";
            echo "?token={$secretToken}&cmd=migrate       -> Menjalankan database migration\n";
            echo "?token={$secretToken}&cmd=seed          -> Menjalankan database seeder\n";
            echo "?token={$secretToken}&cmd=storage_link  -> Menghubungkan storage symlink\n";
            echo "?token={$secretToken}&cmd=cache_clear   -> Membersihkan seluruh cache\n";
            echo "?token={$secretToken}&cmd=optimize      -> Cache config & routes untuk kecepatan maksimal\n";
            break;
    }

    echo $kernel->output();
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";
