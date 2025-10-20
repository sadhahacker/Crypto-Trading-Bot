<?php
session_start();

// Configuration
define('MIN_PHP_VERSION', '8.1.0');
define('REQUIRED_EXTENSIONS', [
    'openssl', 'pdo', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo', 'gmp',
]);
define('REQUIRED_PERMISSIONS', [
    'storage/framework/cache' => 0775,
    'storage/framework/sessions' => 0775,
    'storage/framework/views' => 0775,
    'storage/logs' => 0775,
    'bootstrap/cache' => 0775,
    '.env' => 0664
]);

// Get current step
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step'])) {
        $currentStep = (int)$_POST['step'];

        switch ($currentStep) {
            case 2:
                // Validate requirements passed
                $step = 3;
                break;

            case 3:
                // Save database configuration
                $_SESSION['db_config'] = [
                    'host' => $_POST['db_host'] ?? 'localhost',
                    'port' => $_POST['db_port'] ?? '3306',
                    'database' => $_POST['db_name'] ?? '',
                    'username' => $_POST['db_user'] ?? '',
                    'password' => $_POST['db_pass'] ?? ''
                ];

                // Test database connection
                $dbError = testDatabaseConnection($_SESSION['db_config']);
                if (!$dbError) {
                    $step = 4;
                } else {
                    $_SESSION['db_error'] = $dbError;
                    $step = 3;
                }
                break;

            case 4:
                // Validate passwords match
                if ($_POST['admin_password'] !== $_POST['admin_password_confirm']) {
                    $_SESSION['admin_error'] = 'Passwords do not match';
                    $step = 4;
                    break;
                }

                // Save admin account
                $_SESSION['admin'] = [
                    'name' => $_POST['admin_name'] ?? '',
                    'email' => $_POST['admin_email'] ?? '',
                    'password' => $_POST['admin_password'] ?? ''
                ];
                $step = 5;
                break;

            case 5:
                // Perform installation
                $result = performInstallation();
                if ($result['success']) {
                    $step = 6;
                } else {
                    $_SESSION['install_error'] = $result['error'];
                    $step = 5;
                }
                break;
        }
    }
}

// Helper functions
function checkPhpVersion() {
    return version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=');
}

function checkExtensions() {
    $results = [];
    foreach (REQUIRED_EXTENSIONS as $ext) {
        $results[$ext] = extension_loaded($ext);
    }
    return $results;
}

function checkPermissions() {
    $results = [];
    $baseDir = dirname(__DIR__);

    foreach (REQUIRED_PERMISSIONS as $path => $perm) {
        // Special handling for .env file
        if ($path === '.env') {
            $fullPath = $baseDir . '/' . $path;
            $parentDir = dirname($fullPath);

            if (file_exists($fullPath)) {
                $writable = is_writable($fullPath);
                $results[$path] = ['exists' => true, 'writable' => $writable];
            } else {
                // Check if we can create the file
                $writable = is_writable($parentDir);
                $results[$path] = ['exists' => false, 'writable' => $writable];
            }
        } else {
            $fullPath = $baseDir . '/' . $path;
            $exists = file_exists($fullPath);
            $writable = $exists && is_writable($fullPath);
            $results[$path] = ['exists' => $exists, 'writable' => $writable];
        }
    }
    return $results;
}

function testDatabaseConnection($config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return null;
    } catch (PDOException $e) {
        return $e->getMessage();
    }
}

function performInstallation() {
    try {
        // 1. Create .env file
        $envResult = createEnvFile();
        if (!$envResult['success']) {
            return ['success' => false, 'error' => $envResult['error']];
        }

        // 2. Generate application key
        if (!generateAppKey()) {
            return ['success' => false, 'error' => 'Failed to generate application key'];
        }

        // 3. Run migrations
        $migrateResult = runMigrations();
        if (!$migrateResult['success']) {
            return ['success' => false, 'error' => 'Migration failed: ' . $migrateResult['error']];
        }

        // 4. Create admin user
        if (!createAdminUser()) {
            return ['success' => false, 'error' => 'Failed to create admin user'];
        }

        // 5. Mark as installed
        $storageDir = dirname(__DIR__) . '/storage/framework';
        if (!is_writable($storageDir)) {
            return ['success' => false, 'error' => 'Storage directory is not writable'];
        }
        file_put_contents($storageDir . '/installed', date('Y-m-d H:i:s'));

        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function createEnvFile() {
    $config = $_SESSION['db_config'];
    $envContent = "APP_NAME=Laravel
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost

LOG_CHANNEL=stack

DB_CONNECTION=mysql
DB_HOST={$config['host']}
DB_PORT={$config['port']}
DB_DATABASE={$config['database']}
DB_USERNAME={$config['username']}
DB_PASSWORD={$config['password']}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
";

    $envPath = dirname(__DIR__) . '/.env';
    $parentDir = dirname($envPath);

    // Check if parent directory is writable
    if (!is_writable($parentDir)) {
        return [
            'success' => false,
            'error' => "Cannot write to directory: {$parentDir}. Please run: sudo chown -R www-data:www-data " . dirname(__DIR__)
        ];
    }

    $result = @file_put_contents($envPath, $envContent);

    if ($result === false) {
        $error = error_get_last();
        return [
            'success' => false,
            'error' => "Failed to create .env file: " . ($error['message'] ?? 'Unknown error') . ". Please check file permissions."
        ];
    }

    // Set proper permissions
    @chmod($envPath, 0664);

    return ['success' => true];
}

function generateAppKey() {
    $key = 'base64:' . base64_encode(random_bytes(32));
    $envPath = dirname(__DIR__) . '/.env';
    $envContent = file_get_contents($envPath);
    $envContent = preg_replace('/APP_KEY=.*/', "APP_KEY={$key}", $envContent);
    return file_put_contents($envPath, $envContent) !== false;
}

function runMigrations() {
    $output = [];
    $returnVar = 0;
    $baseDir = dirname(__DIR__);
    exec("cd {$baseDir} && php artisan migrate --force 2>&1", $output, $returnVar);

    if ($returnVar !== 0) {
        return ['success' => false, 'error' => implode("\n", $output)];
    }

    return ['success' => true];
}

function createAdminUser() {
    $admin = $_SESSION['admin'];
    $config = $_SESSION['db_config'];

    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $hashedPassword = password_hash($admin['password'], PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        return $stmt->execute([$admin['name'], $admin['email'], $hashedPassword]);
    } catch (PDOException $e) {
        return false;
    }
}

?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Installation Wizard</title>
    <style>
        :root {
            --background: 0 0% 100%;
            --foreground: 222.2 84% 4.9%;
            --card: 0 0% 100%;
            --card-foreground: 222.2 84% 4.9%;
            --popover: 0 0% 100%;
            --popover-foreground: 222.2 84% 4.9%;
            --primary: 221.2 83.2% 53.3%;
            --primary-foreground: 210 40% 98%;
            --secondary: 210 40% 96.1%;
            --secondary-foreground: 222.2 47.4% 11.2%;
            --muted: 210 40% 96.1%;
            --muted-foreground: 215.4 16.3% 46.9%;
            --accent: 210 40% 96.1%;
            --accent-foreground: 222.2 47.4% 11.2%;
            --destructive: 0 84.2% 60.2%;
            --destructive-foreground: 210 40% 98%;
            --border: 214.3 31.8% 91.4%;
            --input: 214.3 31.8% 91.4%;
            --ring: 221.2 83.2% 53.3%;
            --radius: 0.5rem;
        }

        .dark {
            --background: 222.2 84% 4.9%;
            --foreground: 210 40% 98%;
            --card: 222.2 84% 4.9%;
            --card-foreground: 210 40% 98%;
            --popover: 222.2 84% 4.9%;
            --popover-foreground: 210 40% 98%;
            --primary: 217.2 91.2% 59.8%;
            --primary-foreground: 222.2 47.4% 11.2%;
            --secondary: 217.2 32.6% 17.5%;
            --secondary-foreground: 210 40% 98%;
            --muted: 217.2 32.6% 17.5%;
            --muted-foreground: 215 20.2% 65.1%;
            --accent: 217.2 32.6% 17.5%;
            --accent-foreground: 210 40% 98%;
            --destructive: 0 62.8% 30.6%;
            --destructive-foreground: 210 40% 98%;
            --border: 217.2 32.6% 17.5%;
            --input: 217.2 32.6% 17.5%;
            --ring: 224.3 76.3% 48%;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: hsl(var(--background));
            color: hsl(var(--foreground));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.5;
        }

        .container {
            max-width: 900px;
            width: 100%;
            background: hsl(var(--card));
            border: 1px solid hsl(var(--border));
            border-radius: calc(var(--radius) * 2);
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            overflow: hidden;
        }

        .header {
            background: hsl(var(--card));
            border-bottom: 1px solid hsl(var(--border));
            padding: 40px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: -0.025em;
        }

        .header p {
            color: hsl(var(--muted-foreground));
            font-size: 14px;
        }

        .stepper {
            display: flex;
            padding: 40px;
            background: hsl(var(--card));
            border-bottom: 1px solid hsl(var(--border));
            position: relative;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 20px;
            left: calc(50% + 20px);
            width: calc(100% - 40px);
            height: 2px;
            background: hsl(var(--border));
            z-index: 0;
        }

        .step.completed::after {
            background: hsl(var(--primary));
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: hsl(var(--muted));
            color: hsl(var(--muted-foreground));
            border: 2px solid hsl(var(--border));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 12px;
            position: relative;
            z-index: 2;
            transition: all 0.2s;
        }

        .step.active .step-number {
            background: hsl(var(--primary));
            color: hsl(var(--primary-foreground));
            border-color: hsl(var(--primary));
            box-shadow: 0 0 0 4px hsl(var(--primary) / 0.1);
        }

        .step.completed .step-number {
            background: hsl(var(--primary));
            color: hsl(var(--primary-foreground));
            border-color: hsl(var(--primary));
        }

        .step-label {
            font-size: 13px;
            color: hsl(var(--muted-foreground));
            font-weight: 500;
        }

        .step.active .step-label {
            color: hsl(var(--foreground));
        }

        .content { padding: 48px; }

        .form-group { margin-bottom: 24px; }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: hsl(var(--foreground));
        }

        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid hsl(var(--input));
            background: hsl(var(--background));
            color: hsl(var(--foreground));
            border-radius: var(--radius);
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: hsl(var(--ring));
            box-shadow: 0 0 0 3px hsl(var(--ring) / 0.1);
        }

        .form-group input::placeholder {
            color: hsl(var(--muted-foreground));
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-primary {
            background: hsl(var(--primary));
            color: hsl(var(--primary-foreground));
        }

        .btn-primary:hover:not(:disabled) {
            opacity: 0.9;
        }

        .btn-secondary {
            background: hsl(var(--secondary));
            color: hsl(var(--secondary-foreground));
            border: 1px solid hsl(var(--border));
        }

        .btn-secondary:hover:not(:disabled) {
            background: hsl(var(--accent));
        }

        .actions {
            display: flex;
            justify-content: space-between;
            margin-top: 32px;
            padding-top: 32px;
            border-top: 1px solid hsl(var(--border));
        }

        .check-item {
            padding: 16px;
            margin-bottom: 12px;
            border-radius: var(--radius);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: hsl(var(--muted) / 0.5);
            border: 1px solid hsl(var(--border));
            transition: all 0.2s;
        }

        .check-item:hover {
            border-color: hsl(var(--ring));
        }

        .check-item.pass {
            background: hsl(142.1 76.2% 36.3% / 0.1);
            border-color: hsl(142.1 76.2% 36.3% / 0.3);
        }

        .check-item.fail {
            background: hsl(var(--destructive) / 0.1);
            border-color: hsl(var(--destructive) / 0.3);
        }

        .check-item-label {
            font-size: 14px;
            font-weight: 500;
        }

        .badge {
            padding: 4px 12px;
            border-radius: calc(var(--radius) * 0.75);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .badge-success {
            background: hsl(142.1 76.2% 36.3%);
            color: white;
        }

        .badge-danger {
            background: hsl(var(--destructive));
            color: hsl(var(--destructive-foreground));
        }

        .badge-warning {
            background: hsl(38 92% 50%);
            color: white;
        }

        .alert {
            padding: 16px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            border: 1px solid;
            font-size: 14px;
        }

        .alert-danger {
            background: hsl(var(--destructive) / 0.1);
            color: hsl(var(--destructive-foreground));
            border-color: hsl(var(--destructive) / 0.5);
        }

        .alert-success {
            background: hsl(142.1 76.2% 36.3% / 0.1);
            color: hsl(142.1 76.2% 36.3%);
            border-color: hsl(142.1 76.2% 36.3% / 0.5);
        }

        .alert strong {
            font-weight: 600;
            display: block;
            margin-bottom: 4px;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: hsl(142.1 76.2% 36.3% / 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 40px;
        }

        h2 {
            margin-bottom: 12px;
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.025em;
        }

        h3 {
            margin: 32px 0 16px;
            font-size: 18px;
            font-weight: 600;
        }

        p {
            color: hsl(var(--muted-foreground));
            margin-bottom: 16px;
            font-size: 14px;
        }

        ul {
            margin-left: 24px;
            margin-bottom: 24px;
        }

        li {
            margin-bottom: 8px;
            color: hsl(var(--muted-foreground));
            font-size: 14px;
        }

        .info-box {
            background: hsl(var(--muted) / 0.5);
            border: 1px solid hsl(var(--border));
            padding: 20px;
            border-radius: var(--radius);
            margin: 24px 0;
        }

        .info-box p {
            margin: 0;
            color: hsl(var(--foreground));
        }

        .info-box p:not(:last-child) {
            margin-bottom: 12px;
        }

        .info-box strong {
            font-weight: 600;
        }

        .text-center { text-align: center; }
    </style>
    <!-- Add this inside the .header div, before the closing </div> -->
    <div style="position: absolute; top: 20px; right: 20px;">
        <button id="theme-toggle" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
            🌓 Toggle Theme
        </button>
    </div>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Laravel Installation</h1>
        <p>Setup your application in just a few steps</p>
    </div>

    <div class="stepper">
        <div class="step <?= $step >= 1 ? 'active' : '' ?> <?= $step > 1 ? 'completed' : '' ?>">
            <div class="step-number">1</div>
            <div class="step-label">Welcome</div>
        </div>
        <div class="step <?= $step >= 2 ? 'active' : '' ?> <?= $step > 2 ? 'completed' : '' ?>">
            <div class="step-number">2</div>
            <div class="step-label">Requirements</div>
        </div>
        <div class="step <?= $step >= 3 ? 'active' : '' ?> <?= $step > 3 ? 'completed' : '' ?>">
            <div class="step-number">3</div>
            <div class="step-label">Database</div>
        </div>
        <div class="step <?= $step >= 4 ? 'active' : '' ?> <?= $step > 4 ? 'completed' : '' ?>">
            <div class="step-number">4</div>
            <div class="step-label">Account</div>
        </div>
        <div class="step <?= $step >= 5 ? 'active' : '' ?> <?= $step > 5 ? 'completed' : '' ?>">
            <div class="step-number">5</div>
            <div class="step-label">Install</div>
        </div>
        <div class="step <?= $step >= 6 ? 'active' : '' ?>">
            <div class="step-number">6</div>
            <div class="step-label">Complete</div>
        </div>
    </div>

    <div class="content">
        <?php if ($step === 1): ?>
            <h2>Welcome to Laravel</h2>
            <p>This installation wizard will guide you through setting up your Laravel application. The process is simple and should only take a few minutes.</p>

            <div class="info-box">
                <p><strong>Before you begin, please ensure you have:</strong></p>
                <ul style="margin: 12px 0 0 20px;">
                    <li>MySQL database created and credentials ready</li>
                    <li>PHP <?= MIN_PHP_VERSION ?> or higher installed</li>
                    <li>Required PHP extensions enabled</li>
                    <li>Write permissions for storage directories</li>
                </ul>
            </div>

            <div class="actions">
                <div></div>
                <a href="?step=2" class="btn btn-primary">
                    Get Started
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
            </div>

        <?php elseif ($step === 2): ?>
            <h2>Server Requirements</h2>
            <p>Verifying that your server meets all the necessary requirements for Laravel.</p>

            <h3>PHP Version</h3>
            <?php $phpCheck = checkPhpVersion(); ?>
            <div class="check-item <?= $phpCheck ? 'pass' : 'fail' ?>">
                <span class="check-item-label">PHP <?= MIN_PHP_VERSION ?>+ (Current: <?= PHP_VERSION ?>)</span>
                <span class="badge <?= $phpCheck ? 'badge-success' : 'badge-danger' ?>"><?= $phpCheck ? 'Pass' : 'Fail' ?></span>
            </div>

            <h3>PHP Extensions</h3>
            <?php
            $extensions = checkExtensions();
            $allExtPass = true;
            foreach ($extensions as $ext => $loaded):
                if (!$loaded) $allExtPass = false;
                ?>
                <div class="check-item <?= $loaded ? 'pass' : 'fail' ?>">
                    <span class="check-item-label"><?= $ext ?></span>
                    <span class="badge <?= $loaded ? 'badge-success' : 'badge-danger' ?>"><?= $loaded ? 'Installed' : 'Missing' ?></span>
                </div>
            <?php endforeach; ?>

            <h3>File Permissions</h3>
            <?php
            $permissions = checkPermissions();
            $allPermPass = true;
            foreach ($permissions as $path => $status):
                if (!$status['writable']) $allPermPass = false;
                $statusText = !$status['exists'] ? 'Not Found' : ($status['writable'] ? 'Writable' : 'Not Writable');
                $badgeClass = !$status['exists'] ? 'badge-warning' : ($status['writable'] ? 'badge-success' : 'badge-danger');
                ?>
                <div class="check-item <?= $status['writable'] ? 'pass' : 'fail' ?>">
                    <span class="check-item-label"><?= $path ?></span>
                    <span class="badge <?= $badgeClass ?>"><?= $statusText ?></span>
                </div>
            <?php endforeach; ?>

            <?php if (!$allPermPass): ?>
                <div class="alert alert-danger">
                    <strong>Permission Error</strong>
                    Some directories are not writable. Please run: <code style="display: block; margin-top: 8px; padding: 8px; background: hsl(var(--background)); border-radius: 4px;">sudo chown -R www-data:www-data <?= dirname(__DIR__) ?></code>
                </div>
            <?php endif; ?>

            <div class="actions">
                <a href="?step=1" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    Back
                </a>
                <?php if ($phpCheck && $allExtPass && $allPermPass): ?>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="step" value="2">
                        <button type="submit" class="btn btn-primary">
                            Continue
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>
                    </form>
                <?php else: ?>
                    <a href="?step=2" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                            <path d="M21 3v5h-5"></path>
                            <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                            <path d="M3 21v-5h5"></path>
                        </svg>
                        Recheck
                    </a>
                <?php endif; ?>
            </div>

        <?php elseif ($step === 3): ?>
            <h2>Database Configuration</h2>
            <p>Enter your database connection details. Make sure the database exists and the user has appropriate permissions.</p>

            <?php if (isset($_SESSION['db_error'])): ?>
                <div class="alert alert-danger">
                    <strong>Connection Failed</strong>
                    <?= htmlspecialchars($_SESSION['db_error']) ?>
                </div>
                <?php unset($_SESSION['db_error']); ?>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="step" value="3">

                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="db_host" value="<?= $_SESSION['db_config']['host'] ?? 'localhost' ?>" placeholder="localhost" required>
                </div>

                <div class="form-group">
                    <label>Database Port</label>
                    <input type="text" name="db_port" value="<?= $_SESSION['db_config']['port'] ?? '3306' ?>" placeholder="3306" required>
                </div>

                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="db_name" value="<?= $_SESSION['db_config']['database'] ?? '' ?>" placeholder="laravel" required>
                </div>

                <div class="form-group">
                    <label>Database Username</label>
                    <input type="text" name="db_user" value="<?= $_SESSION['db_config']['username'] ?? '' ?>" placeholder="root" required>
                </div>

                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_pass" value="<?= $_SESSION['db_config']['password'] ?? '' ?>" placeholder="Enter password">
                </div>

                <div class="actions">
                    <a href="?step=2" class="btn btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Back
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Test Connection
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </button>
                </div>
            </form>

        <?php elseif ($step === 4): ?>
            <h2>Administrator Account</h2>
            <p>Create your administrator account. You'll use these credentials to access the application.</p>

            <?php if (isset($_SESSION['admin_error'])): ?>
                <div class="alert alert-danger">
                    <strong>Validation Error</strong>
                    <?= htmlspecialchars($_SESSION['admin_error']) ?>
                </div>
                <?php unset($_SESSION['admin_error']); ?>
            <?php endif; ?>

            <form method="POST" id="adminForm">
                <input type="hidden" name="step" value="4">

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="admin_name" value="<?= $_SESSION['admin']['name'] ?? '' ?>" placeholder="John Doe" required>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="admin_email" value="<?= $_SESSION['admin']['email'] ?? '' ?>" placeholder="admin@example.com" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="admin_password" minlength="8" placeholder="Minimum 8 characters" required>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="admin_password_confirm" minlength="8" placeholder="Re-enter password" required>
                </div>

                <div class="actions">
                    <a href="?step=3" class="btn btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Back
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Continue
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </button>
                </div>
            </form>

        <?php elseif ($step === 5): ?>
            <h2>Ready to Install</h2>
            <p>Everything is configured and ready. Click the button below to begin the installation process.</p>

            <?php if (isset($_SESSION['install_error'])): ?>
                <div class="alert alert-danger">
                    <strong>Installation Failed</strong>
                    <?= htmlspecialchars($_SESSION['install_error']) ?>
                </div>
                <?php unset($_SESSION['install_error']); ?>
            <?php endif; ?>

            <div class="info-box">
                <p><strong>Database:</strong> <?= htmlspecialchars($_SESSION['db_config']['database']) ?></p>
                <p><strong>Host:</strong> <?= htmlspecialchars($_SESSION['db_config']['host']) ?>:<?= htmlspecialchars($_SESSION['db_config']['port']) ?></p>
                <p><strong>Admin Email:</strong> <?= htmlspecialchars($_SESSION['admin']['email']) ?></p>
            </div>

            <form method="POST">
                <input type="hidden" name="step" value="5">
                <div class="actions">
                    <a href="?step=4" class="btn btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Back
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        Install Now
                    </button>
                </div>
            </form>

        <?php elseif ($step === 6): ?>
            <div class="success-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: hsl(142.1 76.2% 36.3%);">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>

            <h2 class="text-center">Installation Complete!</h2>
            <p class="text-center">Your Laravel application has been successfully installed and is ready to use.</p>

            <div class="alert alert-success">
                <strong>Security Notice</strong>
                For security reasons, please delete or rename this installation file immediately before using your application in production.
            </div>

            <div class="info-box">
                <p><strong>Admin Email:</strong> <?= htmlspecialchars($_SESSION['admin']['email']) ?></p>
                <p><strong>Next Steps:</strong></p>
                <ul style="margin: 12px 0 0 20px;">
                    <li>Delete this installer file</li>
                    <li>Log in with your admin credentials</li>
                    <li>Configure your application settings</li>
                    <li>Start building your application</li>
                </ul>
            </div>

            <div class="actions" style="border-top: none; padding-top: 0;">
                <div></div>
                <a href="../" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    Go to Application
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Theme toggle functionality
    document.addEventListener('DOMContentLoaded', () => {
        const html = document.documentElement;
        const toggleBtn = document.getElementById('theme-toggle');

        if (!toggleBtn) return;

        // Load saved theme from localStorage
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            html.classList.add('dark');
        } else if (savedTheme === 'light') {
            html.classList.remove('dark');
        }else {
            html.classList.remove('dark');
        } // else: respect system default (optional)

        // Toggle theme on button click
        toggleBtn.addEventListener('click', () => {
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                html.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
        });
    });
    // Password confirmation validation
    const adminForm = document.getElementById('adminForm');
    if (adminForm) {
        adminForm.addEventListener('submit', function(e) {
            const pass = document.querySelector('[name="admin_password"]').value;
            const confirm = document.querySelector('[name="admin_password_confirm"]').value;
            if (pass !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    }
</script>
</body>
</html>
