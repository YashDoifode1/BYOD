<?php
/**
 * File Manager for Project Files with Permission System
 *
 * Handles file uploads, displays project files, and manages file deletions
 * for authorized users within a specific project with role-based and permission-based access control.
 * Now includes restriction_status checks to prevent access to restricted projects.
 *
 * @author Your Name
 * @version 2.2.0
 */

session_start();
require_once '../includes/auth.php';
require_once '../includes/config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

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

    // Fetch project details with restriction_status check
    $project_stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND restriction_status != 'restricted'");
    $project_stmt->execute([$project_id]);
    $project = $project_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        http_response_code(404);
        die("Project not found or access restricted");
    }

    // Handle file upload
    $upload_errors = [];
    $upload_success = false;
    $disable_upload = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle file upload
        if (isset($_FILES['file'])) {
            // Check if project is restricted (additional safeguard)
            if ($project['restriction_status'] === 'restricted') {
                $upload_errors[] = "Cannot upload files to a restricted project";
                $disable_upload = true;
            }
            // CSRF protection
            elseif (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
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

                    // Create secure upload directory
                    $upload_dir = "uploads/projects/$project_id/";
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0750, true);
                        // Add .htaccess to prevent direct access
                        file_put_contents($upload_dir . '.htaccess', "Deny from all");
                    }

                    $target_path = $upload_dir . $file_name;

                    // Move file if no errors
                    if (empty($upload_errors)) {
                        if (move_uploaded_file($file['tmp_name'], $target_path)) {
                            // Set secure file permissions
                            chmod($target_path, 0640);
                            
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

                            $file_id = $pdo->lastInsertId();

                            // Grant full permissions to the uploader
                            $stmt = $pdo->prepare("
                                INSERT INTO file_permissions 
                                (file_id, user_id, can_view, can_download, can_delete, granted_by) 
                                VALUES (?, ?, 1, 1, 1, ?)
                            ");
                            $stmt->execute([
                                $file_id,
                                $_SESSION['user_id'],
                                $_SESSION['user_id']
                            ]);

                            // Automatically grant view permission to project owner if not the uploader
                            $stmt = $pdo->prepare("
                                SELECT user_id FROM project_members 
                                WHERE project_id = ? AND role = 'owner' AND user_id != ?
                            ");
                            $stmt->execute([$project_id, $_SESSION['user_id']]);
                            $owner = $stmt->fetch();
                            
                            if ($owner) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO file_permissions 
                                    (file_id, user_id, can_view, can_download, can_delete, granted_by) 
                                    VALUES (?, ?, 1, 0, 0, ?)
                                    ON DUPLICATE KEY UPDATE
                                    can_view = 1,
                                    granted_by = VALUES(granted_by),
                                    granted_at = CURRENT_TIMESTAMP
                                ");
                                $stmt->execute([
                                    $file_id,
                                    $owner['user_id'],
                                    $_SESSION['user_id']
                                ]);
                            }

                            // Log activity
                            $stmt = $pdo->prepare("
                                INSERT INTO activity_logs 
                                (user_id, project_id, action, description) 
                                VALUES (?, ?, 'file_upload', ?)
                            ");
                            $stmt->execute([
                                $_SESSION['user_id'],
                                $project_id,
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
        // Handle permission updates
        elseif (isset($_POST['update_permissions'])) {
            if ($project['restriction_status'] === 'restricted') {
                $upload_errors[] = "Cannot modify permissions in a restricted project";
            }
            elseif (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                $upload_errors[] = "Invalid CSRF token";
            } else {
                $file_id = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
                $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                
                if ($file_id && $user_id) {
                    $can_view = isset($_POST['can_view']) ? 1 : 0;
                    $can_download = isset($_POST['can_download']) ? 1 : 0;
                    $can_delete = isset($_POST['can_delete']) ? 1 : 0;
                    
                    // Check if user has permission to modify permissions
                    $stmt = $pdo->prepare("
                        SELECT 1 FROM file_permissions 
                        WHERE file_id = ? AND user_id = ? AND can_delete = 1
                    ");
                    $stmt->execute([$file_id, $_SESSION['user_id']]);
                    $has_permission = $stmt->fetch();
                    
                    // Check if user is project owner or admin
                    $is_owner_or_admin = ($user_project_role === 'owner' || $_SESSION['role'] === 'admin');
                    
                    // Check if target user is the uploader (can't modify uploader's permissions)
                    $stmt = $pdo->prepare("SELECT uploaded_by FROM files WHERE id = ?");
                    $stmt->execute([$file_id]);
                    $uploaded_by = $stmt->fetchColumn();
                    
                    if ($user_id == $uploaded_by) {
                        $upload_errors[] = "Cannot modify permissions for file uploader";
                    }
                    // Check permission to modify
                    elseif (!$has_permission && !$is_owner_or_admin) {
                        $upload_errors[] = "You don't have permission to modify permissions for this file";
                    } else {
                        // Check if permission record exists
                        $stmt = $pdo->prepare("
                            SELECT 1 FROM file_permissions 
                            WHERE file_id = ? AND user_id = ?
                        ");
                        $stmt->execute([$file_id, $user_id]);
                        $exists = $stmt->fetch();
                        
                        if ($exists) {
                            // Update existing permissions
                            $stmt = $pdo->prepare("
                                UPDATE file_permissions 
                                SET can_view = ?, can_download = ?, can_delete = ?, 
                                    granted_by = ?, granted_at = CURRENT_TIMESTAMP
                                WHERE file_id = ? AND user_id = ?
                            ");
                            $stmt->execute([
                                $can_view, $can_download, $can_delete,
                                $_SESSION['user_id'],
                                $file_id, $user_id
                            ]);
                        } else {
                            // Create new permission record
                            $stmt = $pdo->prepare("
                                INSERT INTO file_permissions 
                                (file_id, user_id, can_view, can_download, can_delete, granted_by) 
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $file_id, $user_id, 
                                $can_view, $can_download, $can_delete,
                                $_SESSION['user_id']
                            ]);
                        }
                        
                        // Log activity
                        $stmt = $pdo->prepare("
                            INSERT INTO activity_logs 
                            (user_id, project_id, action, description) 
                            VALUES (?, ?, 'file_permission_update', ?)
                        ");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            $project_id,
                            "Updated permissions for file ID $file_id for user ID $user_id"
                        ]);
                        
                        $upload_success = true;
                    }
                } else {
                    $upload_errors[] = "Invalid file or user ID";
                }
            }
        }
    }

    // Generate CSRF token
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Fetch project files with permissions info (only from non-restricted projects)
    $files_stmt = $pdo->prepare("
        SELECT f.*, u.username, u.first_name, u.last_name,
               MAX(fp.can_view) as can_view, 
               MAX(fp.can_download) as can_download, 
               MAX(fp.can_delete) as can_delete,
               (f.uploaded_by = ?) as is_uploader
        FROM files f 
        JOIN projects p ON f.project_id = p.id
        JOIN users u ON f.uploaded_by = u.id 
        LEFT JOIN file_permissions fp ON (f.id = fp.file_id AND fp.user_id = ?)
        WHERE f.project_id = ? 
        AND p.restriction_status != 'restricted'
        GROUP BY f.id
        ORDER BY f.uploaded_at DESC
    ");
    $files_stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $project_id]);
    $files = $files_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch project members for permission assignment (only from non-restricted projects)
    $members_stmt = $pdo->prepare("
        SELECT u.id, u.username, u.first_name, u.last_name 
        FROM project_members pm
        JOIN users u ON pm.user_id = u.id
        JOIN projects p ON pm.project_id = p.id
        WHERE pm.project_id = ?
        AND p.restriction_status != 'restricted'
    ");
    $members_stmt->execute([$project_id]);
    $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    die("An error occurred while processing your request.");
}

/**
 * Format file size in human-readable format
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

/**
 * Check if user has permission for a file
 */
function has_permission($file, $action, $user_project_role, $session_role) {
    // Admins and project owners have full permissions
    if ($session_role === 'admin' || $user_project_role === 'owner') {
        return true;
    }
    
    // Uploader has all permissions
    if ($file['is_uploader']) {
        return true;
    }
    
    // Check specific permission
    switch ($action) {
        case 'view':
            return (bool)$file['can_view'];
        case 'download':
            return (bool)$file['can_download'];
        case 'delete':
            return (bool)$file['can_delete'];
        default:
            return false;
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            margin-left: 270px;
        }
        .permission-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .permission-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 50%;
            max-width: 600px;
            position: relative;
        }
        .permission-close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 24px;
            cursor: pointer;
        }
        .permission-checkbox {
            margin-right: 10px;
        }
        .permission-user {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .file-viewer-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .file-viewer-content {
            background-color: white;
            margin: 2% auto;
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
            color: #333;
        }
        #fileIframe {
            width: 100%;
            height: calc(100% - 40px);
            border: none;
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6 text-gray-800">File Manager: <?= htmlspecialchars($project['name']) ?></h1>

        <!-- File Upload Form -->
        <?php if (!$disable_upload): ?>
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
                    Operation completed successfully!
                </div>
            <?php endif; ?>

            <form id="upload-form" action="" method="post" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
        <?php else: ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-bold">Restricted Project</p>
                <p>This project has been restricted. File operations are not permitted.</p>
            </div>
        <?php endif; ?>

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
                                        <?php if (has_permission($file, 'view', $user_project_role, $_SESSION['role'])): ?>
                                            <a href="#" data-file-id="<?= $file['id'] ?>" data-file-type="<?= htmlspecialchars($file['mime_type']) ?>" class="view-file text-blue-600 hover:text-blue-800 mr-4">View</a>
                                        <?php endif; ?>
                                        <?php if (has_permission($file, 'download', $user_project_role, $_SESSION['role'])): ?>
                                            <a href="download_file.php?file_id=<?= $file['id'] ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>" class="text-green-600 hover:text-green-800 mr-4">Download</a>
                                        <?php endif; ?>
                                        <?php if (has_permission($file, 'delete', $user_project_role, $_SESSION['role'])): ?>
                                            <form action="delete_file.php" method="post" class="delete-form inline">
                                                <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="project_id" value="<?= $project_id ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($file['is_uploader'] || $user_project_role === 'owner' || $_SESSION['role'] === 'admin'): ?>
                                            <button class="manage-permissions text-purple-600 hover:text-purple-800 ml-4" 
                                                    data-file-id="<?= $file['id'] ?>" 
                                                    data-file-name="<?= htmlspecialchars($file['name']) ?>">
                                                <i class="fas fa-user-shield"></i> Permissions
                                            </button>
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
    <div id="fileViewerModal" class="file-viewer-modal">
        <div id="fileViewerContent" class="file-viewer-content">
            <span id="closeViewer">×</span>
            <iframe id="fileIframe" sandbox="allow-same-origin allow-scripts allow-popups allow-downloads"></iframe>
        </div>
    </div>

    <!-- Permission Management Modal -->
    <div id="permissionModal" class="permission-modal">
        <div class="permission-content">
            <span class="permission-close">×</span>
            <h3 class="text-lg font-semibold mb-4">Manage Permissions for <span id="permissionFileName"></span></h3>
            <input type="hidden" id="permissionFileId">
            <div class="mb-6">
                <h4 class="font-medium mb-2">Project Members</h4>
                <div id="permissionUsersList" class="space-y-2">
                    <!-- Users will be populated here by JavaScript -->
                </div>
            </div>
            <button id="savePermissions" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                Save Permissions
            </button>
        </div>
    </div>

    <script>
    /**
     * Enhanced File Manager JavaScript with Permission Management
     */
    document.addEventListener('DOMContentLoaded', function() {
        // File upload progress handling
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
                        alert('Upload failed. Please try again.');
                    }
                };

                xhr.onerror = () => {
                    console.error('Upload failed');
                    progressContainer.classList.add('hidden');
                    alert('Upload failed due to a network error.');
                };

                const formData = new FormData(uploadForm);
                xhr.send(formData);
            });
        }

        // Delete confirmation handlers
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
                fileIframe.src = `view_file2.php?file_id=${fileId}&csrf_token=<?= $_SESSION['csrf_token'] ?>`;
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

        // Permission Management
        const permissionModal = document.getElementById('permissionModal');
        const permissionClose = document.querySelector('.permission-close');
        const permissionUsersList = document.getElementById('permissionUsersList');
        const permissionFileName = document.getElementById('permissionFileName');
        const permissionFileId = document.getElementById('permissionFileId');
        const savePermissionsBtn = document.getElementById('savePermissions');
        const managePermissionBtns = document.querySelectorAll('.manage-permissions');
        
        // Project members data from PHP
        const projectMembers = <?= json_encode($members) ?>;
        
        // Open permission modal
        managePermissionBtns.forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const fileId = btn.getAttribute('data-file-id');
                const fileName = btn.getAttribute('data-file-name');
                
                permissionFileId.value = fileId;
                permissionFileName.textContent = fileName;
                
                try {
                    const response = await fetch(`get_file_permissions.php?file_id=${fileId}&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>`, {
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    });
                    
                    if (!response.ok) throw new Error('Failed to load permissions');
                    
                    const currentPermissions = await response.json();
                    
                    // Populate users list with checkboxes
                    permissionUsersList.innerHTML = '';
                    
                    projectMembers.forEach(member => {
                        // Skip the uploader (they always have full permissions)
                        const isUploader = currentPermissions.find(p => p.user_id == member.id && p.can_view && p.can_download && p.can_delete);
                        if (isUploader) return;
                        
                        const userPermissions = currentPermissions.find(p => p.user_id == member.id) || {};
                        
                        const userDiv = document.createElement('div');
                        userDiv.className = 'permission-user';
                        
                        userDiv.innerHTML = `
                            <div>
                                <span>${member.first_name} ${member.last_name} (${member.username})</span>
                            </div>
                            <div class="space-x-4">
                                <label>
                                    <input type="checkbox" class="permission-checkbox" 
                                           data-user-id="${member.id}" data-permission="view" 
                                           ${userPermissions.can_view ? 'checked' : ''}>
                                    View
                                </label>
                                <label>
                                    <input type="checkbox" class="permission-checkbox" 
                                           data-user-id="${member.id}" data-permission="download" 
                                           ${userPermissions.can_download ? 'checked' : ''}>
                                    Download
                                </label>
                                <label>
                                    <input type="checkbox" class="permission-checkbox" 
                                           data-user-id="${member.id}" data-permission="delete" 
                                           ${userPermissions.can_delete ? 'checked' : ''}>
                                    Delete
                                </label>
                            </div>
                        `;
                        
                        permissionUsersList.appendChild(userDiv);
                    });
                    
                    permissionModal.style.display = 'block';
                } catch (error) {
                    console.error('Error loading permissions:', error);
                    alert('Failed to load permissions');
                }
            });
        });
        
        // Close permission modal
        permissionClose.addEventListener('click', () => {
            permissionModal.style.display = 'none';
        });
        
        // Save permissions
        savePermissionsBtn.addEventListener('click', async () => {
            const fileId = permissionFileId.value;
            const checkboxes = document.querySelectorAll('.permission-checkbox');
            
            const permissions = [];
            projectMembers.forEach(member => {
                const userPerms = {
                    user_id: member.id,
                    can_view: false,
                    can_download: false,
                    can_delete: false
                };
                
                checkboxes.forEach(checkbox => {
                    if (checkbox.getAttribute('data-user-id') == member.id) {
                        if (checkbox.getAttribute('data-permission') === 'view') {
                            userPerms.can_view = checkbox.checked;
                        }
                        if (checkbox.getAttribute('data-permission') === 'download') {
                            userPerms.can_download = checkbox.checked;
                        }
                        if (checkbox.getAttribute('data-permission') === 'delete') {
                            userPerms.can_delete = checkbox.checked;
                        }
                    }
                });
                
                permissions.push(userPerms);
            });
            
            try {
                const response = await fetch('update_permissions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        file_id: fileId,
                        permissions: permissions,
                        csrf_token: '<?= htmlspecialchars($_SESSION['csrf_token']) ?>'
                    })
                });
                
                if (!response.ok) throw new Error('Failed to save permissions');
                
                const result = await response.json();
                if (result.success) {
                    alert('Permissions updated successfully');
                    permissionModal.style.display = 'none';
                    window.location.reload();
                } else {
                    throw new Error(result.message || 'Failed to save permissions');
                }
            } catch (error) {
                console.error('Error saving permissions:', error);
                alert('Failed to save permissions: ' + error.message);
            }
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === permissionModal) {
                permissionModal.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>

<?php require_once '../includes/footer.php'; ?>