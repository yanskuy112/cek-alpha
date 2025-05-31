<?php
require_once 'config/database.php';

$use_database = true;

// Database functions
function addKegiatan($pdo, $tanggal, $waktu, $kategori, $catatan) {
    try {
        $sql = "INSERT INTO kegiatan_harian (tanggal, waktu, kategori, catatan) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$tanggal, $waktu, $kategori, $catatan]);
    } catch (PDOException $e) {
        error_log("Insert failed: " . $e->getMessage());
        return false;
    }
}

function getRecentKegiatan($pdo, $limit = 3) {
    try {
        $sql = "SELECT * FROM kegiatan_harian ORDER BY tanggal DESC, waktu DESC LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Select failed: " . $e->getMessage());
        return [];
    }
}

function deleteKegiatan($pdo, $id) {
    try {
        $sql = "DELETE FROM kegiatan_harian WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Delete failed: " . $e->getMessage());
        return false;
    }
}

function countKegiatan($pdo) {
    try {
        $sql = "SELECT COUNT(*) as total FROM kegiatan_harian";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch();
        return $result['total'];
    } catch (PDOException $e) {
        error_log("Count failed: " . $e->getMessage());
        return 0;
    }
}

// Fallback to session if database is not available
if (!isset($pdo) || !$use_database) {
    session_start();
    if (!isset($_SESSION['kegiatan_harian'])) {
        $_SESSION['kegiatan_harian'] = [];
    }
    $use_database = false;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = $_POST['tanggal'] ?? '';
    $waktu = $_POST['waktu'] ?? '';
    $kategori = $_POST['kategori'] ?? '';
    $catatan = $_POST['catatan'] ?? '';
    
    if (!empty($tanggal) && !empty($waktu) && !empty($kategori)) {
        if ($use_database && isset($pdo)) {
            if (addKegiatan($pdo, $tanggal, $waktu, $kategori, $catatan)) {
                $success_message = "Data berhasil ditambahkan ke database!";
            } else {
                $error_message = "Gagal menambahkan data ke database!";
            }
        } else {
            // Fallback to session
            $id = count($_SESSION['kegiatan_harian']) + 1;
            $_SESSION['kegiatan_harian'][] = [
                'id' => $id,
                'tanggal' => $tanggal,
                'waktu' => $waktu,
                'kategori' => $kategori,
                'catatan' => $catatan
            ];
            $success_message = "Data berhasil ditambahkan! (Session storage)";
        }
    } else {
        $error_message = "Harap isi semua field yang wajib!";
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    if ($use_database && isset($pdo)) {
        if (deleteKegiatan($pdo, $delete_id)) {
            header('Location: index.php');
            exit;
        }
    } else {
        $_SESSION['kegiatan_harian'] = array_filter($_SESSION['kegiatan_harian'], function($item) use ($delete_id) {
            return $item['id'] !== $delete_id;
        });
        $_SESSION['kegiatan_harian'] = array_values($_SESSION['kegiatan_harian']);
        header('Location: index.php');
        exit;
    }
}

// Get data for display
if ($use_database && isset($pdo)) {
    $recent_data = getRecentKegiatan($pdo, 3);
    $total_count = countKegiatan($pdo);
} else {
    $recent_data = array_slice(array_reverse($_SESSION['kegiatan_harian']), 0, 3);
    $total_count = count($_SESSION['kegiatan_harian']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catatan Kegiatan Harian</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .nav-buttons {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            margin: 0 10px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            font-size: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .form-container {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .data-preview {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .preview-header {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: #333;
            padding: 20px;
            text-align: center;
            font-weight: bold;
            font-size: 18px;
        }
        
        .data-list {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .data-item {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            position: relative;
        }
        
        .data-item:last-child {
            margin-bottom: 0;
        }
        
        .data-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .data-date-time {
            font-weight: bold;
            color: #333;
        }
        
        .data-category {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .data-notes {
            color: #666;
            font-style: italic;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .delete-btn:hover {
            background: #c82333;
        }
        
        .empty-state {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
            }
            
            .content {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .btn {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 Catatan Kegiatan Harian</h1>
            <p>Kelola aktivitas harian Anda dengan mudah</p>
        </div>
        
        <div class="content">
            <div class="nav-buttons">
                <a href="index.php" class="btn btn-primary">📝 Input Data</a>
                <a href="view.php" class="btn btn-secondary">📊 Lihat Semua Data</a>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">✅ <?= $success_message ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">❌ <?= $error_message ?></div>
            <?php endif; ?>
            
            <div class="form-container">
                <h2 style="margin-bottom: 25px; color: #333; text-align: center;">Tambah Kegiatan Baru</h2>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="tanggal">📅 Tanggal *</label>
                        <input type="date" id="tanggal" name="tanggal" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="waktu">🕒 Waktu *</label>
                        <input type="time" id="waktu" name="waktu" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="kategori">📂 Kategori *</label>
                        <select id="kategori" name="kategori" class="form-control" required>
                            <option value="">-- Pilih Kategori --</option>
                            <option value="Competitive Trading">💹 Competitive Trading</option>
                            <option value="Fee">💰 Fee</option>
                            <option value="Cair AirDrop">🎁 Cair AirDrop</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="catatan">📝 Catatan</label>
                        <textarea id="catatan" name="catatan" class="form-control" placeholder="Tambahkan catatan atau detail kegiatan..."></textarea>
                    </div>
                    
                    <div style="text-align: center;">
                        <button type="submit" class="btn btn-primary">✨ Tambah Kegiatan</button>
                    </div>
                </form>
            </div>
            
            <?php if (!empty($recent_data)): ?>
                <div class="data-preview">
                    <div class="preview-header">
                        📋 Preview Data Terbaru (<?= $total_count ?> kegiatan) 
                        <?= $use_database ? '🗄️' : '📝' ?>
                    </div>
                    <div class="data-list">
                        <?php foreach ($recent_data as $item): ?>
                            <div class="data-item">
                                <div class="data-item-header">
                                    <div class="data-date-time">
                                        📅 <?= date('d/m/Y', strtotime($item['tanggal'])) ?> - 🕒 <?= $item['waktu'] ?>
                                    </div>
                                    <div>
                                        <span class="data-category"><?= htmlspecialchars($item['kategori']) ?></span>
                                        <a href="?delete=<?= $item['id'] ?>" class="delete-btn" onclick="return confirm('Yakin ingin menghapus data ini?')">🗑️</a>
                                    </div>
                                </div>
                                <?php if (!empty($item['catatan'])): ?>
                                    <div class="data-notes">💭 <?= htmlspecialchars($item['catatan']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ($total_count > 3): ?>
                            <div style="text-align: center; margin-top: 20px;">
                                <a href="view.php" class="btn btn-secondary">Lihat Semua Data (<?= $total_count ?>)</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="data-preview">
                    <div class="empty-state">
                        🌟 Belum ada kegiatan yang dicatat.<br>
                        Mulai tambahkan kegiatan pertama Anda!<br>
                        <small style="color: #666; margin-top: 10px; display: block;">
                            <?= $use_database ? '🗄️ Menggunakan Database' : '📝 Menggunakan Session Storage' ?>
                        </small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>