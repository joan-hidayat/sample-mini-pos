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
