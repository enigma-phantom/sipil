<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Path direktori utama
$rootDir = dirname(__DIR__) . '/test-kompres/upload';

// Path yang saat ini diakses
$currentDir = isset($_GET['dir']) ? rtrim($_GET['dir'], '/') : $rootDir;

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$base_url = $protocol . $_SERVER['SERVER_NAME'] . dirname($_SERVER["REQUEST_URI"] . '?') . '/';

// Fungsi untuk menampilkan daftar file dan direktori
function listContents($dir)
{
    if (!is_dir($dir)) {
        return [];
    }
    return array_diff(scandir($dir), array('.', '..'));
}

// Fungsi untuk menampilkan apakah itu file atau direktori
function isDirectory($path)
{
    return is_dir($path);
}

// Fungsi untuk membuat link agar user bisa masuk ke direktori tertentu
function createLink($dir, $item)
{
    return "index.php?dir=" . urlencode($dir . '/' . $item);
}

// Fungsi untuk kompresi gambar menggunakan GD
function compressImageGD($source, $quality)
{
    $info = getimagesize($source);
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            return imagejpeg($image, $source, $quality);
        case 'image/png':
            $image = imagecreatefrompng($source);
            // PNG mendukung nilai 0-9 untuk kualitas
            return imagepng($image, $source, round($quality / 10)); // ubah 0-100 menjadi 0-9
        case 'image/gif':
            $image = imagecreatefromgif($source);
            return imagegif($image, $source);
        default:
            return false; // Format tidak didukung
    }
}

// Fungsi untuk kompresi gambar dalam folder secara rekursif
function compressImagesInFolder($folder, $quality)
{
    $compressedCount = 0;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder));

    foreach ($files as $file) {
        if ($file->isFile()) {
            $filePath = $file->getRealPath();
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                if (compressImageGD($filePath, $quality)) {
                    $compressedCount++;
                }
            }
        }
    }

    // Menyusun hasil kompresi
    return [
        'success' => $compressedCount > 0,
        'compressedCount' => $compressedCount
    ];
}

// Proses kompresi berdasarkan permintaan
$message = ''; // Variabel untuk menyimpan pesan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quality = (int)$_POST['quality']; // Mendapatkan kualitas dari form

    // Cek apakah kualitas valid
    if ($quality < 0 || $quality > 100) {
        $message = "Kualitas harus antara 0 hingga 100.";
    } else {
        $result = compressImagesInFolder($currentDir, $quality);
        // Menampilkan pesan sesuai hasil kompresi
        if ($result['success']) {
            $message = "Kompresi berhasil! {$result['compressedCount']} gambar terkompresi.";
        } else {
            $message = "Tidak ada gambar yang ditemukan atau kompresi gagal.";
        }
    }
}

function formatSize($bytes)
{
    if ($bytes < 1024) {
        return $bytes . ' bytes';
    } elseif ($bytes < 1048576) {
        return round($bytes / 1024, 2) . ' KB';
    } elseif ($bytes < 1073741824) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes < 1099511627776) {
        return round($bytes / 1073741824, 2) . ' GB';
    } else {
        return round($bytes / 1099511627776, 2) . ' TB';
    }
}

function getFolderSize($dir)
{
    $size = 0;
    foreach (scandir($dir) as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $dir . '/' . $file;
            if (is_dir($filePath)) {
                // Rekursi jika item adalah direktori
                $size += getFolderSize($filePath);
            } else {
                // Tambahkan ukuran file
                $size += filesize($filePath);
            }
        }
    }
    return $size;
}

$totalSize = 0;

// Ambil isi direktori saat ini
$contents = listContents($currentDir);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>SIPIL - Sistem Image Pengompresan Interaktif dan Linier</title>
    <link rel="stylesheet" href="./style.css">
</head>

<body>
    <div class="container-fluid pt-2">

        <h1 class="fh-title">SIPIL - Sistem Image Pengompresan Interaktif dan Linier</h1>
        <div
            class="alert alert-primary"
            role="alert">
            <strong>Perhatian!</strong> Aplikasi ini dibuat hanya untuk kebutuhan mengkompresi image secara recursive file didalam folder path rootDir yang telah diset sebelumnya <b>[$rootDir = dirname(__DIR__) . '/test-kompres/upload';]</b>, setelah digunakan harap dihapus kembali agar tidak terjadi kesalahan penggunaan aplikasi ini <b>(Pada Mode Production)</b>.
        </div>
        <div
            class="alert alert-danger"
            role="alert">
            <strong>Penting!</strong> Jangan lupa untuk mengubah <b>$rootDir</b> ke folder root directory anda, untuk menghindari terjadi kesalahan pengompresan file gambar.
        </div>
        <!-- Tampilkan pesan -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Tampilkan current directory -->
        <p>Direktori Sekarang : <span class="badge bg-primary"><?php echo htmlspecialchars($currentDir); ?></span></p>

        <!-- Form untuk memilih kualitas kompresi -->
        <form method="POST" action="index.php?dir=<?php echo urlencode($currentDir); ?>">
            <div class="row g-3 align-items-center">
                <div class="col-auto">
                    <label for="inputQuality" class="col-form-label">Kualitas Kompresi (0-100) : </label>
                </div>
                <div class="col-auto">
                    <input type="number" id="quality" name="quality" class="form-control" value="70" min="0" max="100" aria-describedby="qualityHelpInline" required>
                </div>
                <div class="col-auto">
                    <span id="qualityHelpInline" class="form-text">
                        Semakin rendah nilai, semakin besar kompresi.
                    </span>
                </div>
            </div>
            <button type="submit" class="btn btn-sm my-2 btn-warning" name="compress_images">Compress Images</button>
        </form>
        <div class="py-2">
            <?php if ($currentDir == $rootDir): ?>
                <p>Anda sudah berada di direktori root.</p>
            <?php else: ?>
                <div class="d-flex align-items-center mb-2">
                    <a href="<?php echo createLink(dirname($currentDir), ''); ?>" class="btn btn-sm btn-default border">&larr; Kembali</a>
                    <button type="button" name="btnRefresh" id="btnRefresh" class="btn btn-sm btn-success ms-2" onclick="window.location.assign('<?= $base_url ?>')">Refresh ke Home Dir</button>
                </div>
            <?php endif; ?>
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">List Folder dan File.</h4>
                    <table class="table table-striped table-responsive w-100 fh-table">
                        <tr>
                            <th>Nama Item</th>
                            <th>Tipe</th>
                            <th>Ukuran</th> <!-- Kolom baru untuk ukuran -->
                        </tr>
                        <?php foreach ($contents as $item): ?>
                            <tr>
                                <td>
                                    <?php if (isDirectory($currentDir . '/' . $item)): ?>
                                        <!-- Link untuk masuk ke dalam subdirektori -->
                                        <a href="<?php echo createLink($currentDir, $item); ?>">
                                            <?php echo htmlspecialchars($item); ?>
                                        </a>
                                    <?php else: ?>
                                        <!-- Hanya tampilkan nama file -->
                                        <?php echo htmlspecialchars($item); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <!-- Tampilkan apakah itu file atau direktori -->
                                    <?php echo isDirectory($currentDir . '/' . $item) ? 'Directory' : 'File'; ?>
                                </td>
                                <td>
                                    <?php if (isDirectory($currentDir . '/' . $item)): ?>
                                        <!-- Gunakan fungsi getFolderSize untuk mendapatkan ukuran folder -->
                                        <?php
                                        $folderSize = getFolderSize($currentDir . '/' . $item);
                                        $totalSize += $folderSize; // Tambahkan ukuran folder ke total
                                        echo formatSize($folderSize);
                                        ?>
                                    <?php else: ?>
                                        <!-- Tampilkan ukuran file dengan filesize -->
                                        <?php
                                        $fileSize = filesize($currentDir . '/' . $item);
                                        $totalSize += $fileSize; // Tambahkan ukuran file ke total
                                        echo formatSize($fileSize);
                                        ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <div class="alert alert-info mt-2">
                <strong>Total ukuran yang ada didalam directory ini : </strong><?php echo formatSize($totalSize); ?>
            </div>

        </div>

        <footer class="d-flex flex-wrap justify-content-center align-items-center py-3 border-top">
            <div class="col-md-12 d-flex justify-content-center align-items-center">
                <a href="/" class="mb-3 me-2 mb-md-0 text-body-secondary text-decoration-none lh-1">
                    <svg class="bi" width="30" height="24">
                        <use xlink:href="#bootstrap" />
                    </svg>
                </a>
                <span class="mb-3 mb-md-0 text-body-secondary">Made with &hearts; by Panda Developer.</span>
            </div>
        </footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="./stub.js"></script>
</body>

</html>