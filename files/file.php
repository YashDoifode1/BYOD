<?php
/**
 * File Manager for Project Files
 *
 * Handles file uploads, displays project files, and manages file deletions
 * for authorized users within a specific project with role-based access control.
 * Includes file viewing in iframe for all users.
 *
 * @author Your Name
 * @version 1.2.0
 */

session_start();
require_once '..\includes/auth.php';
require_once '..\includes/config.php';
require_once '..\includes/header.php';
require_once '..\includes/sidebar.php';

// Validate project ID
$project_id = filter_input(INPUT_GET, 'project_id', FILTER_VALIDATE_INT);
if (!$project_id) {
    http_response_code(400);
    die("Invalid project ID");
}

try {
    // Check user access to project and get role
    $stmt = $pdo->prepare("SELECT role FROM project_members WHERE project_id = ? AND user_id = ?");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$member) {
        http_response_code(403);
        die("You don't have access to this project");
    }
    
    // Store user's project role
    $user_project_role = $member['role'];

    // Fetch project details
    $project_stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $project_stmt->execute([$project_id]);
    $project = $project_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        http_response_code(404);
        die("Project not found");
    }

    // Handle file upload
    $upload_errors = [];
    $upload_success = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
        // CSRF protection
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $upload_errors[] = "Invalid CSRF token";
        } else {
            $file = $_FILES['file'];

            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $upload_errors[] = "File upload error: " . $file['error'];
            } else {
                // Validate file type
                $allowed_types = [
                    'image/jpeg', 'image/png', 'image/gif',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/plain'
                ];

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($file['tmp_name']);

                if (!in_array($mime_type, $allowed_types)) {
                    $upload_errors[] = "Invalid file type. Allowed types: images, PDF, Word, Excel, text files.";
                }

                // Check file size (10MB max)
                $max_size = 10 * 1024 * 1024; // 10MB
                if ($file['size'] > $max_size) {
                    $upload_errors[] = "File too large. Maximum size is 10MB.";
                }

                // Sanitize filename
                $original_name = basename($file['name']);
                $file_name = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $original_name);
                $file_name = time() . '_' . $file_name;

                // Create project directory
                $upload_dir = "uploads/projects/$project_id/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $target_path = $upload_dir . $file_name;

                // Move file if no errors
                if (empty($upload_errors)) {
                    if (move_uploaded_file($file['tmp_name'], $target_path)) {
                        // Save to database
                        $stmt = $pdo->prepare("
                            INSERT INTO files 
                            (project_id, name, path, size, mime_type, uploaded_by) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $project_id,
                            $original_name,
                            $target_path,
                            $file['size'],
                            $mime_type,
                            $_SESSION['user_id']
                        ]);

                        // Log activity
                        $stmt = $pdo->prepare("
                            INSERT INTO activity_logs 
                            (user_id, project_id, action, description) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            $project_id,
                            'file_upload',
                            "Uploaded file: $original_name"
                        ]);

                        $upload_success = true;
                    } else {
                        $upload_errors[] = "Failed to move uploaded file.";
                    }
                }
            }
        }
    }

    // Generate CSRF token
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Fetch project files
    $files_stmt = $pdo->prepare("
        SELECT f.*, u.username, u.first_name, u.last_name 
        FROM files f 
        JOIN users u ON f.uploaded_by = u.id 
        WHERE f.project_id = ? 
        ORDER BY f.uploaded_at DESC
    ");
    $files_stmt->execute([$project_id]);
    $files = $files_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    die("An error occurred while processing your request.");
}

/**
 * Format file size in human-readable format
 *
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return number_format($bytes, 0) . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager: <?= htmlspecialchars($project['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Custom styles for file manager */
        body{
            margin-left:280px;
        }
        .progress-container {
            position: relative;
            height: 1.25rem;
            background-color: #e5e7eb;
            border-radius: 0.375rem;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background-color: #2563eb;
            border-radius: 0.375rem;
            transition: width 0.3s ease;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 0.75rem 1.5rem;
            text-align: left;
        }

        th {
            background-color: #f9fafb;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            color: #6b7280;
        }

        tr:hover {
            background-color: #f9fafb;
        }

        .delete-form button {
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
        }

        #fileViewerModal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        #fileViewerContent {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 1200px;
            height: 80%;
            position: relative;
        }

        #closeViewer {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 24px;
            cursor: pointer;
        }

        #fileIframe {
            width: 100%;
            height: 90%;
            border: none;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6 text-gray-800">File Manager: <?= htmlspecialchars($project['name']) ?></h1>

        <!-- File Upload Form -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-lg font-semibold mb-4 text-gray-700">Upload New File</h2>
            <?php if (!empty($upload_errors)): ?>
                <div class="bg-red-50 text-red-700 p-4 rounded-md mb-4">
                    <?php foreach ($upload_errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($upload_success): ?>
                <div class="bg-green-50 text-green-700 p-4 rounded-md mb-4">
                    File uploaded successfully!
                </div>
            <?php endif; ?>

            <form id="upload-form" action="" method="post" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div>
                    <label for="file" class="block text-sm font-medium text-gray-700">Select file (max 10MB):</label>
                    <input type="file" name="file" id="file" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Upload</button>
                <div id="progress-container" class="hidden bg-gray-200 rounded-md h-5 relative">
                    <div id="progress-bar" class="bg-blue-600 h-full rounded-md"></div>
                    <span id="progress-text" class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-xs text-white font-bold">0%</span>
                </div>
            </form>
        </div>

        <!-- File List -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-lg font-semibold mb-4 text-gray-700">Project Files</h2>
            <?php if (empty($files)): ?>
                <p class="text-gray-600">No files uploaded yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($files as $file): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($file['name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($file['mime_type']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= format_file_size($file['size']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($file['first_name'] . ' ' . $file['last_name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('M d, Y H:i', strtotime($file['uploaded_at'])) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="#" data-file-id="<?= $file['id'] ?>" class="view-file text-blue-600 hover:text-blue-800 mr-4">View</a>
                                        <?php if ($user_project_role === 'manager' || $_SESSION['role'] === 'admin'): ?>
                                            <a href="<?= htmlspecialchars($file['path']) ?>" download class="text-green-600 hover:text-green-800 mr-4">Download</a>
                                            <form action="delete_file.php" method="post" class="delete-form inline">
                                                <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="project_id" value="<?= $project_id ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- File Viewer Modal -->
    <div id="fileViewerModal">
        <div id="fileViewerContent">
            <span id="closeViewer">&times;</span>
            <iframe id="fileIframe" sandbox="allow-same-origin allow-scripts"></iframe>
        </div>
    </div>

    <script>
        /**
         * File Manager JavaScript
         *
         * Handles file upload progress, deletion confirmations, and file viewing in modal.
         */

        /**
         * Initialize upload form handler
         */
        const uploadForm = document.querySelector('#upload-form');
        if (uploadForm) {
            uploadForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const progressContainer = document.querySelector('#progress-container');
                const progressBar = document.querySelector('#progress-bar');
                const progressText = document.querySelector('#progress-text');

                progressContainer.classList.remove('hidden');

                const xhr = new XMLHttpRequest();
                xhr.open('POST', uploadForm.action, true);

                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        progressBar.style.width = `${percent}%`;
                        progressText.textContent = `${percent}%`;
                    }
                };

                xhr.onload = () => {
                    if (xhr.status === 200) {
                        window.location.reload();
                    } else {
                        console.error('Upload failed:', xhr.status);
                        progressContainer.classList.add('hidden');
                    }
                };

                xhr.onerror = () => {
                    console.error('Upload failed');
                    progressContainer.classList.add('hidden');
                };

                const formData = new FormData(uploadForm);
                xhr.send(formData);
            });
        }

        /**
         * Initialize delete confirmation handlers
         */
        const deleteForms = document.querySelectorAll('form.delete-form');
        deleteForms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!window.confirm('Are you sure you want to delete this file?')) {
                    e.preventDefault();
                }
            });
        });

        /**
         * Initialize file viewer handlers
         */
        const viewLinks = document.querySelectorAll('.view-file');
        const fileViewerModal = document.querySelector('#fileViewerModal');
        const fileIframe = document.querySelector('#fileIframe');
        const closeViewer = document.querySelector('#closeViewer');

        viewLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const fileId = link.getAttribute('data-file-id');
                fileIframe.src = `view_file.php?file_id=${fileId}&csrf_token=<?= $_SESSION['csrf_token'] ?>`;
                fileViewerModal.style.display = 'block';
            });
        });

        closeViewer.addEventListener('click', () => {
            fileViewerModal.style.display = 'none';
            fileIframe.src = '';
        });

        // Close modal when clicking outside content
        window.addEventListener('click', (e) => {
            if (e.target === fileViewerModal) {
                fileViewerModal.style.display = 'none';
                fileIframe.src = '';
            }
        });
    </script>
</body>
</html>

<?php require_once '..\includes/footer.php'; ?>