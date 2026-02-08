<?php
$ADMIN_USER = getenv('ADMIN_USER') ?: 'admin';
$ADMIN_PASS = getenv('ADMIN_PASS') ?: 'change-me';
$USING_DEFAULT_AUTH = (getenv('ADMIN_USER') === false || getenv('ADMIN_PASS') === false);

if (
    !isset($_SERVER['PHP_AUTH_USER']) ||
    !isset($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] !== $ADMIN_USER ||
    $_SERVER['PHP_AUTH_PW'] !== $ADMIN_PASS
) {
    header('WWW-Authenticate: Basic realm="Admin Area"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication required.';
    exit;
}

$dataPathCandidates = [
    __DIR__ . '/../../src/admin/generators.json',
    __DIR__ . '/generators.json',
];

$dataPath = null;
$writableTargets = [];
foreach ($dataPathCandidates as $candidate) {
    if (file_exists($candidate)) {
        if ($dataPath === null) {
            $dataPath = $candidate;
        }
        if (is_writable($candidate)) {
            $writableTargets[] = $candidate;
        }
    }
}

if ($dataPath === null) {
    http_response_code(500);
    echo 'Generator data file not found.';
    exit;
}

$error = '';
$success = '';
$generators = [];

function normalize_lines($value) {
    $lines = preg_split('/\r\n|\r|\n/', trim($value));
    $clean = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $clean[] = $line;
        }
    }
    return $clean;
}

function normalize_specs($value) {
    $lines = preg_split('/\r\n|\r|\n/', trim($value));
    $specs = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = explode(':', $line, 2);
        $label = trim($parts[0]);
        $specValue = isset($parts[1]) ? trim($parts[1]) : '';
        if ($label !== '') {
            $specs[$label] = $specValue;
        }
    }
    return $specs;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = isset($_POST['id']) ? $_POST['id'] : [];
    $count = is_array($ids) ? count($ids) : 0;
    $updated = [];

    for ($i = 0; $i < $count; $i++) {
        $updated[] = [
            'id' => trim($_POST['id'][$i] ?? ''),
            'name' => trim($_POST['name'][$i] ?? ''),
            'power' => trim($_POST['power'][$i] ?? ''),
            'fuel' => trim($_POST['fuel'][$i] ?? ''),
            'phase' => trim($_POST['phase'][$i] ?? ''),
            'voltage' => trim($_POST['voltage'][$i] ?? ''),
            'image' => trim($_POST['image'][$i] ?? ''),
            'type' => trim($_POST['type'][$i] ?? ''),
            'description' => trim($_POST['description'][$i] ?? ''),
            'status' => trim($_POST['status'][$i] ?? ''),
            'category' => trim($_POST['category'][$i] ?? ''),
            'features' => normalize_lines($_POST['features'][$i] ?? ''),
            'keyFeatures' => normalize_lines($_POST['keyFeatures'][$i] ?? ''),
            'specs' => normalize_specs($_POST['specs'][$i] ?? ''),
        ];
    }

    $json = json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $error = 'Could not encode data.';
    } else {
        $saved = false;
        foreach ($writableTargets as $target) {
            if (file_put_contents($target, $json) !== false) {
                $saved = true;
            }
        }
        if ($saved) {
            $success = 'Product data saved successfully.';
        } else {
            $error = 'Could not write to the data file. Check permissions.';
        }
    }
}

$raw = file_get_contents($dataPath);
if ($raw !== false) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $generators = $decoded;
    } else {
        $error = 'The data file contains invalid JSON.';
    }
} else {
    $error = 'Could not read the data file.';
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Rental Catalog Admin</title>
        <link rel="stylesheet" href="/admin/admin.css">
    </head>
    <body>
        <main class="admin">
            <header class="admin-header">
                <div>
                    <p class="admin-eyebrow">Rental Catalog Admin</p>
                    <h1>Manage Generator Products</h1>
                    <p class="admin-subtitle">Update product details used to generate the rental catalog pages.</p>
                </div>
                <a class="admin-link" href="/catalog/">View Catalog</a>
            </header>

            <?php if ($error): ?>
                <div class="admin-alert admin-alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="admin-alert admin-alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($USING_DEFAULT_AUTH): ?>
                <div class="admin-alert admin-alert-error">Default admin credentials are in use. Set ADMIN_USER and ADMIN_PASS in the server environment.</div>
            <?php endif; ?>

            <form method="post" class="admin-form">
                <?php foreach ($generators as $index => $gen): ?>
                    <section class="admin-card">
                        <div class="admin-card-header">
                            <h2><?php echo htmlspecialchars($gen['name'] ?? 'Generator'); ?></h2>
                            <span class="admin-pill"><?php echo htmlspecialchars($gen['id'] ?? ''); ?></span>
                        </div>
                        <div class="admin-grid">
                            <label>
                                ID
                                <input type="text" name="id[]" value="<?php echo htmlspecialchars($gen['id'] ?? ''); ?>" required>
                            </label>
                            <label>
                                Name
                                <input type="text" name="name[]" value="<?php echo htmlspecialchars($gen['name'] ?? ''); ?>" required>
                            </label>
                            <label>
                                Power
                                <input type="text" name="power[]" value="<?php echo htmlspecialchars($gen['power'] ?? ''); ?>">
                            </label>
                            <label>
                                Fuel
                                <input type="text" name="fuel[]" value="<?php echo htmlspecialchars($gen['fuel'] ?? ''); ?>">
                            </label>
                            <label>
                                Phase
                                <input type="text" name="phase[]" value="<?php echo htmlspecialchars($gen['phase'] ?? ''); ?>">
                            </label>
                            <label>
                                Voltage
                                <input type="text" name="voltage[]" value="<?php echo htmlspecialchars($gen['voltage'] ?? ''); ?>">
                            </label>
                            <label>
                                Image Path
                                <input type="text" name="image[]" value="<?php echo htmlspecialchars($gen['image'] ?? ''); ?>">
                            </label>
                            <label>
                                Type
                                <input type="text" name="type[]" value="<?php echo htmlspecialchars($gen['type'] ?? ''); ?>">
                            </label>
                            <label>
                                Status
                                <input type="text" name="status[]" value="<?php echo htmlspecialchars($gen['status'] ?? ''); ?>">
                            </label>
                            <label>
                                Category
                                <input type="text" name="category[]" value="<?php echo htmlspecialchars($gen['category'] ?? ''); ?>">
                            </label>
                        </div>
                        <label class="admin-block">
                            Description
                            <textarea name="description[]" rows="4"><?php echo htmlspecialchars($gen['description'] ?? ''); ?></textarea>
                        </label>
                        <div class="admin-columns">
                            <label class="admin-block">
                                Features (one per line)
                                <textarea name="features[]" rows="6"><?php echo htmlspecialchars(implode("\n", $gen['features'] ?? [])); ?></textarea>
                            </label>
                            <label class="admin-block">
                                Key Features (one per line)
                                <textarea name="keyFeatures[]" rows="6"><?php echo htmlspecialchars(implode("\n", $gen['keyFeatures'] ?? [])); ?></textarea>
                            </label>
                        </div>
                        <label class="admin-block">
                            Specs (one per line: Label: Value)
                            <textarea name="specs[]" rows="6"><?php
                                $specLines = [];
                                if (isset($gen['specs']) && is_array($gen['specs'])) {
                                    foreach ($gen['specs'] as $label => $value) {
                                        $specLines[] = $label . ': ' . $value;
                                    }
                                }
                                echo htmlspecialchars(implode("\n", $specLines));
                            ?></textarea>
                        </label>
                    </section>
                <?php endforeach; ?>

                <div class="admin-actions">
                    <button type="submit">Save Changes</button>
                    <p class="admin-note">Changes update the data file. Regenerate the site to refresh product pages.</p>
                </div>
            </form>
        </main>
    </body>
</html>

