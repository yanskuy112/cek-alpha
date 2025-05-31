<?php
require_once 'config/database.php';

$use_database = true;

// Database functions
function getAllKegiatan($pdo, $category_filter = '') {
    try {
        if (!empty($category_filter)) {
            $sql = "SELECT * FROM kegiatan_harian WHERE kategori = ? ORDER BY tanggal DESC, waktu DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$category_filter]);
        } else {
            $sql = "SELECT * FROM kegiatan_harian ORDER BY tanggal DESC, waktu DESC";
            $stmt = $pdo->query($sql);
        }
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Select failed: " . $e->getMessage());
        return [];
    }
}

function getCategoryStats($pdo) {
    try {
        $sql = "SELECT kategori, COUNT(*) as count FROM kegiatan_harian GROUP BY kategori";
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll();
        
        $stats = [];
        foreach ($results as $row) {
            $stats[$row['kategori']] = $row['count'];
        }
        return $stats;
    } catch (PDOException $e) {
        error_log("Stats failed: " . $e->getMessage());
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

function clearAllKegiatan($pdo) {
    try {
        $sql = "DELETE FROM kegiatan_harian";
        $stmt = $pdo->query($sql);
        return true;
    } catch (PDOException $e) {
        error_log("Clear all failed: " . $e->getMessage());
        return false;
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

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    if ($use_database && isset($pdo)) {
        deleteKegiatan($pdo, $delete_id);
    } else {
        $_SESSION['kegiatan_harian'] = array_filter($_SESSION['kegiatan_harian'], function($item) use ($delete_id) {
            return $item['id'] !== $delete_id;
        });
        $_SESSION['kegiatan_harian'] = array_values($_SESSION['kegiatan_harian']);
    }
    header('Location: view.php');
    exit;
}

// Handle clear all data
if (isset($_GET['clear_all']) && $_GET['clear_all'] === 'confirm') {
    if ($use_database && isset($pdo)) {
        clearAllKegiatan($pdo);
    } else {
        $_SESSION['kegiatan_harian'] = [];
    }
    header('Location: view.php');
    exit;
}

// Get data
$filter_category = $_GET['filter'] ?? '';

if ($use_database && isset($pdo)) {
    $sorted_data = getAllKegiatan($pdo, $filter_category);
    $category_stats = getCategoryStats($pdo);
    $total_count = array_sum($category_stats);
} else {
    // Fallback to session
    $sorted_data = $_SESSION['kegiatan_harian'];
    usort($sorted_data, function($a, $b) {
        $datetime_a = $a['tanggal'] . ' ' . $a['waktu'];
        $datetime_b = $b['tanggal'] . ' ' . $b['waktu'];
        return strcmp($datetime_b, $datetime_a);
    });
    
    if (!empty($filter_category)) {
        $sorted_data = array_filter($sorted_data, function($item) use ($filter_category) {
            return $item['kategori'] === $filter_category;
        });
    }
    
    // Get category statistics
    $category_stats = [];
    foreach ($_SESSION['kegiatan_harian'] as $item) {
        $cat = $item['kategori'];
        $category_stats[$cat] = ($category_stats[$cat] ?? 0) + 1;
    }
    $total_count = count($_SESSION['kegiatan_harian']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Kegiatan Harian</title>
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
            max-width: 1200px;
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
        
        .btn-danger {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: white;
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 15px;
        }
        
        .filter-label {
            font-weight: bold;
            color: #333;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .filter-btn.active {
            background: #667eea;
            color: white;
        }
        
        .filter-btn:not(.active) {
            background: white;
            color: #333;
            border: 2px solid #e1e5e9;
        }
        
        .filter-btn:hover {
            transform: translateY(-1px);
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: #333;
            padding: 20px 15px;
            text-align: left;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: top;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .category-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        
        .category-competitive { background: #007bff; }
        .category-fee { background: #28a745; }
        .category-airdrop { background: #ffc107; color: #333; }
        
        .notes-cell {
            max-width: 200px;
            word-wrap: break-word;
            color: #666;
            font-style: italic;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.3s ease;
        }
        
        .delete-btn:hover {
            background: #c82333;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #666;
        }
        
        .empty-state h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .table-responsive {
            overflow-x: auto;
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
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .table th,
            .table td {
                padding: 10px 8px;
                font-size: 14px;
            }
            
            .notes-cell {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Data Kegiatan Harian</h1>
            <p>Laporan lengkap aktivitas Anda</p>
        </div>
        
        <div class="content">
            <div class="nav-buttons">
                <a href="index.php" class="btn btn-primary">📝 Tambah Data</a>
                <a href="view.php" class="btn btn-secondary">📊 Lihat Data</a>
                <?php if (!empty($sorted_data) || $total_count > 0): ?>
                    <a href="?clear_all=confirm" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus SEMUA data? Tindakan ini tidak dapat dibatalkan!')">🗑️ Hapus Semua</a>
                <?php endif; ?>
                <div style="margin-top: 10px; text-align: center;">
                    <small style="color: #666;">
                        <?= $use_database ? '🗄️ Database Mode' : '📝 Session Mode' ?>
                    </small>
                </div>
            </div>
            
            <?php if (!empty($sorted_data) || $total_count > 0): ?>
                <!-- Statistics -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_count ?></div>
                        <div class="stat-label">Total Kegiatan</div>
                    </div>
                    <?php foreach ($category_stats as $category => $count): ?>
                        <div class="stat-card">
                            <div class="stat-number"><?= $count ?></div>
                            <div class="stat-label"><?= htmlspecialchars($category) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Filters -->
                <div class="filters">
                    <span class="filter-label">🔍 Filter Kategori:</span>
                    <a href="view.php" class="filter-btn <?= empty($filter_category) ? 'active' : '' ?>">Semua</a>
                    <a href="?filter=Competitive Trading" class="filter-btn <?= $filter_category === 'Competitive Trading' ? 'active' : '' ?>">💹 Competitive Trading</a>
                    <a href="?filter=Fee" class="filter-btn <?= $filter_category === 'Fee' ? 'active' : '' ?>">💰 Fee</a>
                    <a href="?filter=Cair AirDrop" class="filter-btn <?= $filter_category === 'Cair AirDrop' ? 'active' : '' ?>">🎁 Cair AirDrop</a>
                </div>
                
                <!-- Data Table -->
                <div class="table-container">
                    <?php if (!empty($sorted_data)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>📅 Tanggal</th>
                                        <th>🕒 Waktu</th>
                                        <th>📂 Kategori</th>
                                        <th>📝 Catatan</th>
                                        <th>⚡ Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sorted_data as $item): ?>
                                        <tr>
                                            <td><strong><?= date('d/m/Y', strtotime($item['tanggal'])) ?></strong><br>
                                                <small style="color: #666;"><?= date('l', strtotime($item['tanggal'])) ?></small>
                                            </td>
                                            <td><strong><?= $item['waktu'] ?></strong></td>
                                            <td>
                                                <?php
                                                $badge_class = '';
                                                switch($item['kategori']) {
                                                    case 'Competitive Trading':
                                                        $badge_class = 'category-competitive';
                                                        $icon = '💹';
                                                        break;
                                                    case 'Fee':
                                                        $badge_class = 'category-fee';
                                                        $icon = '💰';
                                                        break;
                                                    case 'Cair AirDrop':
                                                        $badge_class = 'category-airdrop';
                                                        $icon = '🎁';
                                                        break;
                                                    default:
                                                        $badge_class = 'category-competitive';
                                                        $icon = '📂';
                                                }
                                                ?>
                                                <span class="category-badge <?= $badge_class ?>">
                                                    <?= $icon ?> <?= htmlspecialchars($item['kategori']) ?>
                                                </span>
                                            </td>
                                            <td class="notes-cell">
                                                <?= !empty($item['catatan']) ? '💭 ' . htmlspecialchars($item['catatan']) : '<em style="color: #aaa;">Tidak ada catatan</em>' ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="delete-btn" onclick="if(confirm('Yakin ingin menghapus data ini?')) { window.location.href='?delete=<?= $item['id'] ?>'; }">
                                                        🗑️ Hapus
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>🔍 Tidak ada data dengan filter ini</h3>
                            <p>Coba ubah filter atau tambah data baru.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3>📝 Belum ada data kegiatan</h3>
                    <p>Mulai tambahkan kegiatan harian Anda!</p>
                    <br>
                    <a href="index.php" class="btn btn-primary">✨ Tambah Kegiatan Pertama</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
                
        // Auto refresh setiap 30 detik jika ada data
        <?php if (!empty($sorted_data) || $total_count > 0): ?>
        // Uncomment line below if you want auto refresh
        // setTimeout(() => { location.reload(); }, 30000);
        <?php endif; ?>
        
        // Smooth scroll for better UX
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Add loading state for delete buttons
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.innerHTML = '⏳ Menghapus...';
                this.disabled = true;
            });
        });
    </script>
</body>
</html>