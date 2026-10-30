<?php
/**
 * Transaksi kasir & laporan penjualan per tenant (T-28).
 * Harga dan total selalu dihitung ulang di server dari tabel products; browser hanya mengirim id + qty.
 */
class SaleRepository {
    const TAX_RATE = 0.11;
    const MAX_LINES = 50;
    const MAX_QTY = 999;

    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * $items: [['id' => productId, 'qty' => n], ...]. $trackStock: lisensi punya modul STOK.
     * Mengembalikan transaksi lengkap + stok terbaru produk yang terjual.
     */
    public function checkout($tenantId, $userId, array $items, $trackStock) {
        $quantities = $this->normalizeItems($items);

        $this->db->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($quantities), '?'));
            $stmt = $this->db->prepare("
                SELECT id, name, price, stock FROM products
                WHERE tenant_id = ? AND status = 'active' AND id IN ({$placeholders})
                FOR UPDATE
            ");
            $stmt->execute(array_merge([$tenantId], array_keys($quantities)));
            $products = [];
            foreach ($stmt->fetchAll() as $row) {
                $products[(int) $row['id']] = $row;
            }
            if (count($products) !== count($quantities)) {
                throw new InvalidArgumentException('Sebagian produk sudah tidak tersedia. Muat ulang halaman kasir.');
            }

            if ($trackStock) {
                $short = [];
                foreach ($quantities as $id => $qty) {
                    if ((int) $products[$id]['stock'] < $qty) {
                        $short[] = "{$products[$id]['name']} (sisa " . max(0, (int) $products[$id]['stock']) . ')';
                    }
                }
                if ($short) {
                    throw new InvalidArgumentException('Stok tidak cukup: ' . implode(', ', $short) . '.');
                }
            }

            $lines = [];
            $subtotal = 0;
            foreach ($quantities as $id => $qty) {
                $price = (int) $products[$id]['price'];
                $lines[] = ['product_id' => $id, 'product_name' => $products[$id]['name'], 'price' => $price, 'qty' => $qty, 'line_total' => $price * $qty];
                $subtotal += $price * $qty;
            }
            $tax = (int) round($subtotal * self::TAX_RATE);

            $this->db->prepare("INSERT INTO sales (tenant_id, user_id, subtotal, tax, total) VALUES (:tenant_id, :user_id, :subtotal, :tax, :total)")
                ->execute(['tenant_id' => $tenantId, 'user_id' => $userId, 'subtotal' => $subtotal, 'tax' => $tax, 'total' => $subtotal + $tax]);
            $saleId = (int) $this->db->lastInsertId();

            $insertItem = $this->db->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, price, qty, line_total) VALUES (:sale_id, :product_id, :product_name, :price, :qty, :line_total)");
            $decrement = $this->db->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id AND tenant_id = :tenant_id");
            $stock = [];
            foreach ($lines as $line) {
                $insertItem->execute(['sale_id' => $saleId] + $line);
                if ($trackStock) {
                    $decrement->execute(['qty' => $line['qty'], 'id' => $line['product_id'], 'tenant_id' => $tenantId]);
                    $stock[$line['product_id']] = (int) $products[$line['product_id']]['stock'] - $line['qty'];
                }
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $sale = $this->find($saleId, $tenantId);
        $sale['items'] = $lines;
        $sale['stock'] = $stock;
        return $sale;
    }

    public function find($saleId, $tenantId) {
        $stmt = $this->db->prepare("SELECT id, subtotal, tax, total, created_at FROM sales WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute(['id' => $saleId, 'tenant_id' => $tenantId]);
        $sale = $stmt->fetch();
        if (!$sale) {
            return null;
        }
        $sale['invoice'] = self::invoiceNo($sale);
        return $sale;
    }

    public static function invoiceNo(array $sale) {
        return sprintf('TRX-%s-%06d', date('Ymd', strtotime($sale['created_at'])), $sale['id']);
    }

    /**
     * Ringkasan penjualan untuk rentang tanggal (inklusif), format Y-m-d.
     */
    public function report($tenantId, $from, $to) {
        $range = ['tenant_id' => $tenantId, 'from' => $from . ' 00:00:00', 'to' => date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
        $where = "s.tenant_id = :tenant_id AND s.created_at >= :from AND s.created_at < :to";

        $summary = $this->fetchOne("SELECT COUNT(*) AS transactions, COALESCE(SUM(s.total), 0) AS revenue, COALESCE(SUM(s.tax), 0) AS tax FROM sales s WHERE {$where}", $range);
        $summary = array_map('intval', $summary);
        $summary['average'] = $summary['transactions'] ? (int) round($summary['revenue'] / $summary['transactions']) : 0;

        return [
            'summary' => $summary,
            'daily' => $this->fetchAll("SELECT DATE(s.created_at) AS day, COUNT(*) AS transactions, SUM(s.total) AS revenue FROM sales s WHERE {$where} GROUP BY DATE(s.created_at) ORDER BY day DESC", $range),
            'products' => $this->fetchAll("
                SELECT i.product_name, SUM(i.qty) AS qty, SUM(i.line_total) AS revenue
                FROM sale_items i JOIN sales s ON s.id = i.sale_id
                WHERE {$where} GROUP BY i.product_name ORDER BY qty DESC, revenue DESC LIMIT 10", $range),
            'cashiers' => $this->fetchAll("
                SELECT COALESCE(u.name, '(user dihapus)') AS name, COUNT(*) AS transactions, SUM(s.total) AS revenue
                FROM sales s LEFT JOIN users u ON u.id = s.user_id
                WHERE {$where} GROUP BY s.user_id, u.name ORDER BY revenue DESC", $range),
            'recent' => array_map(function ($sale) {
                $sale['invoice'] = self::invoiceNo($sale);
                return $sale;
            }, $this->fetchAll("
                SELECT s.id, s.total, s.created_at, COALESCE(u.name, '(user dihapus)') AS cashier,
                       (SELECT SUM(qty) FROM sale_items WHERE sale_id = s.id) AS items
                FROM sales s LEFT JOIN users u ON u.id = s.user_id
                WHERE {$where} ORDER BY s.id DESC LIMIT 20", $range)),
        ];
    }

    /**
     * Menggabungkan baris produk yang sama dan memvalidasi qty. Mengembalikan [productId => qty].
     */
    private function normalizeItems(array $items) {
        $quantities = [];
        foreach ($items as $item) {
            $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $qty = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_QTY]]);
            if ($id === false || $qty === false) {
                throw new InvalidArgumentException('Data keranjang tidak valid.');
            }
            $quantities[$id] = ($quantities[$id] ?? 0) + $qty;
            if ($quantities[$id] > self::MAX_QTY) {
                throw new InvalidArgumentException('Jumlah per produk maksimal ' . self::MAX_QTY . '.');
            }
        }
        if (!$quantities) {
            throw new InvalidArgumentException('Keranjang masih kosong.');
        }
        if (count($quantities) > self::MAX_LINES) {
            throw new InvalidArgumentException('Maksimal ' . self::MAX_LINES . ' produk per transaksi.');
        }
        return $quantities;
    }

    private function fetchOne($sql, array $params) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    private function fetchAll($sql, array $params) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
