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
$uploadNotice = '';
$uploadErrors = [];
$hasUploadErrors = false;

$uploadDir = __DIR__ . '/../images/rental_uploads';
$uploadWebPath = '/images/rental_uploads';
$maxUploadBytes = 5 * 1024 * 1024;
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$uploadReady = true;
if (!is_dir($uploadDir)) {
    $uploadReady = mkdir($uploadDir, 0755, true);
}
if (!$uploadReady || !is_writable($uploadDir)) {
    $uploadReady = false;
    $uploadNotice = 'Image uploads are disabled because the upload directory is not writable.';
}

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
    $imageUploads = isset($_FILES['image_upload']) ? $_FILES['image_upload'] : null;
    $hasError = false;
    $uploadErrors = [];
    $hasUploadErrors = false;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    for ($i = 0; $i < $count; $i++) {
        $imagePath = trim($_POST['image'][$i] ?? '');

        if ($imageUploads && isset($imageUploads['tmp_name'][$i]) && $imageUploads['tmp_name'][$i] !== '') {
            $uploadErrorMessage = '';
            if (!$uploadReady) {
                $uploadErrorMessage = 'Image upload failed because the upload directory is not writable.';
            } elseif ($imageUploads['error'][$i] !== UPLOAD_ERR_OK) {
                $uploadErrorMessage = 'Image upload failed with error code ' . $imageUploads['error'][$i] . '.';
            } elseif (($imageUploads['size'][$i] ?? 0) > $maxUploadBytes) {
                $uploadErrorMessage = 'Image upload failed because the file is larger than 5MB.';
            } elseif (!$finfo) {
                $uploadErrorMessage = 'Image upload failed because the server cannot validate file type.';
            } else {
                $originalName = $imageUploads['name'][$i] ?? '';
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $detectedMime = finfo_file($finfo, $imageUploads['tmp_name'][$i]);
                if (!in_array($detectedMime, $allowedMime, true)) {
                    $uploadErrorMessage = 'Invalid image file type. Use JPG, PNG, GIF, or WEBP.';
                } elseif (!in_array($extension, $allowedExtensions, true)) {
                    $uploadErrorMessage = 'Invalid image file extension. Use JPG, PNG, GIF, or WEBP.';
                } else {
                    $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_POST['id'][$i] ?? ''));
                    if ($safeId === '') {
                        $safeId = 'product';
                    }
                    $token = bin2hex(random_bytes(4));
                    $filename = $safeId . '-' . $token . '.' . $extension;
                    $targetPath = $uploadDir . '/' . $filename;
                    if (move_uploaded_file($imageUploads['tmp_name'][$i], $targetPath)) {
                        $imagePath = $uploadWebPath . '/' . $filename;
                    } else {
                        $uploadErrorMessage = 'Could not save the uploaded image. Check server permissions.';
                    }
                }
            }

            if ($uploadErrorMessage !== '') {
                $uploadErrors[$i] = $uploadErrorMessage;
                $hasUploadErrors = true;
            }
        }

        $updated[] = [
            'id' => trim($_POST['id'][$i] ?? ''),
            'name' => trim($_POST['name'][$i] ?? ''),
            'power' => trim($_POST['power'][$i] ?? ''),
            'fuel' => trim($_POST['fuel'][$i] ?? ''),
            'phase' => trim($_POST['phase'][$i] ?? ''),
            'voltage' => trim($_POST['voltage'][$i] ?? ''),
            'image' => $imagePath,
            'type' => trim($_POST['type'][$i] ?? ''),
            'description' => trim($_POST['description'][$i] ?? ''),
            'status' => trim($_POST['status'][$i] ?? ''),
            'category' => trim($_POST['category'][$i] ?? ''),
            'features' => normalize_lines($_POST['features'][$i] ?? ''),
            'keyFeatures' => normalize_lines($_POST['keyFeatures'][$i] ?? ''),
            'specs' => normalize_specs($_POST['specs'][$i] ?? ''),
        ];
    }

    if (!$hasError) {
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
    if ($finfo) {
        finfo_close($finfo);
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
            <?php if (isset($hasUploadErrors) && $hasUploadErrors): ?>
                <div class="admin-alert admin-alert-warning">Some uploads failed. Review item warnings.</div>
            <?php endif; ?>
            <?php if ($USING_DEFAULT_AUTH): ?>
                <div class="admin-alert admin-alert-error">Default admin credentials are in use. Set ADMIN_USER and ADMIN_PASS in the server environment.</div>
            <?php endif; ?>
            <?php if ($uploadNotice): ?>
                <div class="admin-alert admin-alert-error"><?php echo htmlspecialchars($uploadNotice); ?></div>
            <?php endif; ?>

            <form method="post" class="admin-form" enctype="multipart/form-data">
                <div class="admin-toolbar">
                    <label class="admin-search">
                        Search Products
                        <input type="search" id="admin-search-input" placeholder="Search by name, ID, category, power...">
                    </label>
                    <div class="admin-toolbar-meta">
                        <span id="admin-search-count" class="admin-count"></span>
                        <button type="button" id="admin-clear-search" class="admin-clear">Clear</button>
                    </div>
                </div>
                <?php foreach ($generators as $index => $gen): ?>
                    <?php
                        $featureText = '';
                        if (isset($gen['features']) && is_array($gen['features'])) {
                            $featureText = implode(' ', $gen['features']);
                        }
                        $keyFeatureText = '';
                        if (isset($gen['keyFeatures']) && is_array($gen['keyFeatures'])) {
                            $keyFeatureText = implode(' ', $gen['keyFeatures']);
                        }
                        $searchParts = [
                            $gen['id'] ?? '',
                            $gen['name'] ?? '',
                            $gen['category'] ?? '',
                            $gen['power'] ?? '',
                            $gen['fuel'] ?? '',
                            $gen['type'] ?? '',
                            $gen['status'] ?? '',
                            $gen['voltage'] ?? '',
                            $gen['description'] ?? '',
                            $featureText,
                            $keyFeatureText,
                        ];
                        $searchData = strtolower(implode(' ', $searchParts));
                    ?>
                    <section class="admin-card" data-search="<?php echo htmlspecialchars($searchData); ?>">
                        <div class="admin-card-header">
                            <h2><?php echo htmlspecialchars($gen['name'] ?? 'Generator'); ?></h2>
                            <span class="admin-pill"><?php echo htmlspecialchars($gen['id'] ?? ''); ?></span>
                        </div>
                        <?php if (isset($uploadErrors) && isset($uploadErrors[$index])): ?>
                            <div class="admin-upload-error">
                                <strong>Upload failed:</strong> <?php echo htmlspecialchars($uploadErrors[$index]); ?>
                            </div>
                        <?php endif; ?>
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
                                Upload Image
                                <input
                                    type="file"
                                    name="image_upload[]"
                                    accept="image/*"
                                    class="admin-image-upload"
                                    data-preview-target="image-preview-<?php echo $index; ?>"
                                    data-meta-target="file-meta-<?php echo $index; ?>"
                                    data-reset-target="image-reset-<?php echo $index; ?>"
                                >
                                <div class="admin-file-meta" id="file-meta-<?php echo $index; ?>">No file selected.</div>
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
                        <div class="admin-image-preview">
                            <span>Current Image</span>
                            <img id="image-preview-<?php echo $index; ?>" src="<?php echo htmlspecialchars($gen['image'] ?? ''); ?>" alt="" data-current-src="<?php echo htmlspecialchars($gen['image'] ?? ''); ?>">
                            <button type="button" class="admin-reset-preview" id="image-reset-<?php echo $index; ?>">Reset preview</button>
                            <p class="admin-help">Uploading a file will replace the image path after you save.</p>
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
                <div class="admin-empty-state" id="admin-empty-state">
                    No products match your search. Try another keyword.
                </div>

                <div class="admin-actions">
                    <button type="submit">Save Changes</button>
                    <p class="admin-note">Changes update the data file. Regenerate the site to refresh product pages.</p>
                </div>
            </form>
        </main>
        <script>
            const searchInput = document.getElementById('admin-search-input');
            const clearButton = document.getElementById('admin-clear-search');
            const countLabel = document.getElementById('admin-search-count');
            const cards = Array.from(document.querySelectorAll('.admin-card'));
            const emptyState = document.getElementById('admin-empty-state');
            const maxUploadBytes = 5 * 1024 * 1024;

            const updateCount = () => {
                const visible = cards.filter((card) => card.style.display !== 'none').length;
                const total = cards.length;
                countLabel.textContent = `Showing ${visible} of ${total}`;
                if (emptyState) {
                    emptyState.style.display = visible === 0 ? 'block' : 'none';
                }
            };

            const filterCards = () => {
                const term = (searchInput.value || '').trim().toLowerCase();
                cards.forEach((card) => {
                    const haystack = card.dataset.search || '';
                    card.style.display = haystack.includes(term) ? '' : 'none';
                });
                updateCount();
            };

            if (searchInput) {
                searchInput.addEventListener('input', filterCards);
            }

            if (clearButton) {
                clearButton.addEventListener('click', () => {
                    searchInput.value = '';
                    filterCards();
                    searchInput.focus();
                });
            }

            const formatFileSize = (bytes) => {
                if (bytes < 1024) {
                    return `${bytes} B`;
                }
                if (bytes < 1024 * 1024) {
                    return `${(bytes / 1024).toFixed(1)} KB`;
                }
                return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
            };

            document.querySelectorAll('.admin-image-upload').forEach((input) => {
                const metaId = input.getAttribute('data-meta-target');
                const meta = metaId ? document.getElementById(metaId) : null;
                const resetId = input.getAttribute('data-reset-target');
                const resetButton = resetId ? document.getElementById(resetId) : null;
                const previewId = input.getAttribute('data-preview-target');
                const preview = previewId ? document.getElementById(previewId) : null;

                const resetMeta = () => {
                    if (meta) {
                        meta.textContent = 'No file selected.';
                        meta.classList.remove('is-warning');
                    }
                };

                resetMeta();

                input.addEventListener('change', (event) => {
                    const file = event.target.files && event.target.files[0];
                    if (meta) {
                        if (file) {
                            meta.textContent = `${file.name} • ${formatFileSize(file.size)}`;
                            meta.classList.toggle('is-warning', file.size > maxUploadBytes);
                        } else {
                            resetMeta();
                        }
                    }
                    if (preview && file) {
                        preview.src = URL.createObjectURL(file);
                    }
                });

                if (resetButton && preview) {
                    resetButton.addEventListener('click', () => {
                        const currentSrc = preview.getAttribute('data-current-src') || '';
                        preview.src = currentSrc;
                        input.value = '';
                        resetMeta();
                    });
                }
            });

            updateCount();
        </script>
    </body>
</html>

