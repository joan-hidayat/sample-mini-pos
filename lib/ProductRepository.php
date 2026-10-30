<?php
/**
 * Produk toko per tenant. Katalog contoh dibuat otomatis untuk tenant yang belum punya produk.
 * Stok hanya dipakai bila lisensi memiliki modul STOK (T-28).
 */
class ProductRepository {
    const DEFAULT_CATALOG = [
        ['Kopi Espresso Blend', 'Minuman', '☕', 18000],
        ['Matcha Latte Premium', 'Minuman', '🍵', 24000],
        ['Croissant Coklat', 'Pastry', '🥐', 22000],
        ['Sandwich Daging Asap', 'Makanan', '🥪', 28000],
        ['Air Mineral Prima 600ml', 'Minuman', '💧', 6000],
        ['Donat Glaze Vanilla', 'Camilan', '🍩', 12000],
    ];
    const DEFAULT_STOCK = 50;
    const MAX_STOCK = 1000000;
    const MAX_PRICE = 100000000;

    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function listActive($tenantId) {
        $this->ensureCatalog($tenantId);
        $stmt = $this->db->prepare("SELECT id, name, category, emoji, price, stock, min_stock FROM products WHERE tenant_id = :tenant_id AND status = 'active' ORDER BY id");
        $stmt->execute(['tenant_id' => $tenantId]);
        return array_map([$this, 'cast'], $stmt->fetchAll());
    }

    /**
     * Semua produk toko (aktif & nonaktif) untuk halaman Kelola Produk.
     */
    public function listAll($tenantId) {
        $this->ensureCatalog($tenantId);
        $stmt = $this->db->prepare("SELECT * FROM products WHERE tenant_id = :tenant_id ORDER BY status, category, name");
        $stmt->execute(['tenant_id' => $tenantId]);
        return array_map([$this, 'cast'], $stmt->fetchAll());
    }

    /**
     * Produk baru. Stok awal hanya diisi bila toko memakai modul STOK; tanpa modul itu stok 0
     * dan diatur nanti di halaman Stok setelah upgrade.
     */
    public function create($tenantId, array $input, $stock = 0) {
        $data = $this->validate($tenantId, $input);
        if (filter_var($stock, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => self::MAX_STOCK]]) === false) {
            throw new InvalidArgumentException('Stok awal harus angka 0 – ' . number_format(self::MAX_STOCK, 0, ',', '.') . '.');
        }
        $this->db->prepare("INSERT INTO products (tenant_id, name, category, emoji, price, stock) VALUES (:tenant_id, :name, :category, :emoji, :price, :stock)")
            ->execute(['tenant_id' => $tenantId, 'stock' => (int) $stock] + $data);
        return $this->find((int) $this->db->lastInsertId(), $tenantId);
    }

    /**
     * Mengubah nama, kategori, emoji, harga. Transaksi lama tidak berubah (sale_items menyimpan salinan nama & harga).
     */
    public function update($productId, $tenantId, array $input) {
        if (!$this->find($productId, $tenantId)) {
            throw new InvalidArgumentException('Produk tidak ditemukan.');
        }
        $data = $this->validate($tenantId, $input, $productId);
        $this->db->prepare("UPDATE products SET name = :name, category = :category, emoji = :emoji, price = :price WHERE id = :id AND tenant_id = :tenant_id")
            ->execute(['id' => $productId, 'tenant_id' => $tenantId] + $data);
        return $this->find($productId, $tenantId);
    }

    /**
     * Produk tidak dihapus permanen (dirujuk transaksi); nonaktif = hilang dari kasir.
     */
    public function setStatus($productId, $tenantId, $status) {
        if (!in_array($status, ['active', 'inactive'], true) || !$this->find($productId, $tenantId)) {
            throw new InvalidArgumentException('Produk tidak ditemukan.');
        }
        $this->db->prepare("UPDATE products SET status = :status WHERE id = :id AND tenant_id = :tenant_id")
            ->execute(['status' => $status, 'id' => $productId, 'tenant_id' => $tenantId]);
        return $this->find($productId, $tenantId);
    }

    /**
     * Mengatur stok & stok minimum (halaman Stok). Mengembalikan produk setelah diubah.
     */
    public function updateStock($productId, $tenantId, $stock, $minStock) {
        foreach (['Stok' => $stock, 'Stok minimum' => $minStock] as $label => $value) {
            if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => self::MAX_STOCK]]) === false) {
                throw new InvalidArgumentException("{$label} harus angka 0 – " . number_format(self::MAX_STOCK, 0, ',', '.') . '.');
            }
        }
        $product = $this->find($productId, $tenantId);
        if (!$product) {
            throw new InvalidArgumentException('Produk tidak ditemukan.');
        }
        $this->db->prepare("UPDATE products SET stock = :stock, min_stock = :min_stock WHERE id = :id AND tenant_id = :tenant_id")
            ->execute(['stock' => (int) $stock, 'min_stock' => (int) $minStock, 'id' => $productId, 'tenant_id' => $tenantId]);
        return $this->find($productId, $tenantId);
    }

    public function find($productId, $tenantId) {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute(['id' => $productId, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();
        return $row ? $this->cast($row) : null;
    }

    private function validate($tenantId, array $input, $exceptId = null) {
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($input['name'] ?? '')));
        $category = trim((string) ($input['category'] ?? '')) ?: 'Umum';
        $emoji = trim((string) ($input['emoji'] ?? ''));
        $price = filter_var($input['price'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_PRICE]]);

        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            throw new InvalidArgumentException('Nama produk 2 – 150 karakter.');
        }
        if (mb_strlen($category) > 80) {
            throw new InvalidArgumentException('Kategori maksimal 80 karakter.');
        }
        if (mb_strlen($emoji) > 8) {
            throw new InvalidArgumentException('Ikon cukup 1 emoji.');
        }
        if ($price === false) {
            throw new InvalidArgumentException('Harga harus angka 1 – ' . number_format(self::MAX_PRICE, 0, ',', '.') . ' (rupiah, tanpa titik).');
        }
        // Nama unik per toko: laporan "produk terlaris" dikelompokkan per nama
        $stmt = $this->db->prepare("SELECT id FROM products WHERE tenant_id = :tenant_id AND name = :name AND id <> :except_id");
        $stmt->execute(['tenant_id' => $tenantId, 'name' => $name, 'except_id' => (int) $exceptId]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException("Produk \"{$name}\" sudah ada.");
        }
        return ['name' => $name, 'category' => $category, 'emoji' => $emoji !== '' ? $emoji : null, 'price' => $price];
    }

    private function ensureCatalog($tenantId) {
        $stmt = $this->db->prepare("SELECT 1 FROM products WHERE tenant_id = :tenant_id LIMIT 1");
        $stmt->execute(['tenant_id' => $tenantId]);
        if ($stmt->fetch()) {
            return;
        }
        $insert = $this->db->prepare("INSERT INTO products (tenant_id, name, category, emoji, price, stock) VALUES (:tenant_id, :name, :category, :emoji, :price, :stock)");
        foreach (self::DEFAULT_CATALOG as [$name, $category, $emoji, $price]) {
            $insert->execute(['tenant_id' => $tenantId, 'name' => $name, 'category' => $category, 'emoji' => $emoji, 'price' => $price, 'stock' => self::DEFAULT_STOCK]);
        }
    }

    private function cast(array $row) {
        foreach (['id', 'price', 'stock', 'min_stock'] as $key) {
            $row[$key] = (int) $row[$key];
        }
        return $row;
    }
}
