<?php

function money(float $amount): string
{
    return CURRENCY . ' ' . number_format($amount, 0);
}

function get_categories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY sort_order')->fetchAll();
}

function get_products_with_stock(array $filters = []): array
{
    $sql = 'SELECT p.*, c.name AS category_name,
                   (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY sort_order LIMIT 1) AS thumbnail,
                   (SELECT COALESCE(SUM(stock), 0) FROM product_variants pv WHERE pv.product_id = p.id) AS total_stock
            FROM products p
            JOIN categories c ON c.id = p.category_id
            WHERE p.status = "active"';
    $params = [];

    if (!empty($filters['search'])) {
        $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ?)';
        $params[] = '%' . $filters['search'] . '%';
        $params[] = '%' . $filters['search'] . '%';
    }
    if (!empty($filters['low_stock_only'])) {
        $sql .= ' HAVING total_stock <= p.reorder_level';
    }

    $sql .= ' ORDER BY p.name';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_variants_for_product(int $productId): array
{
    $stmt = db()->prepare('SELECT * FROM product_variants WHERE product_id = ? ORDER BY color, size');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function get_variant(int $variantId): ?array
{
    $stmt = db()->prepare('SELECT pv.*, p.name AS product_name, p.price, p.sku, p.on_sale, p.discount_percent
                            FROM product_variants pv JOIN products p ON p.id = pv.product_id
                            WHERE pv.id = ?');
    $stmt->execute([$variantId]);
    $variant = $stmt->fetch() ?: null;
    if ($variant) {
        $variant['effective_price'] = ($variant['on_sale'] && $variant['discount_percent'] !== null)
            ? round($variant['price'] * (1 - $variant['discount_percent'] / 100), 2)
            : (float) $variant['price'];
    }
    return $variant;
}

function social_platform_style(string $platform): array
{
    $key = strtolower(trim($platform));
    $map = [
        'whatsapp'  => ['icon' => 'chat', 'color' => '#25D366'],
        'instagram' => ['icon' => 'photo_camera', 'color' => '#E1306C'],
        'tiktok'    => ['icon' => 'music_note', 'color' => '#000000'],
        'tiktok shop' => ['icon' => 'storefront', 'color' => '#000000'],
        'facebook'  => ['icon' => 'groups', 'color' => '#1877F2'],
        'twitter'   => ['icon' => 'tag', 'color' => '#000000'],
        'x'         => ['icon' => 'tag', 'color' => '#000000'],
        'youtube'   => ['icon' => 'smart_display', 'color' => '#FF0000'],
    ];
    return $map[$key] ?? ['icon' => 'link', 'color' => '#0F172A'];
}

function get_social_links(): array
{
    return db()->query('SELECT * FROM social_links ORDER BY sort_order')->fetchAll();
}

function render_social_bar(array $links, string $size = 'normal'): void
{
    if (empty($links)) {
        return;
    }
    $pad = $size === 'small' ? 'px-3 py-1.5 text-xs' : 'px-4 py-2 text-sm';
    echo '<div class="flex flex-wrap justify-center gap-2">';
    foreach ($links as $link) {
        $style = social_platform_style($link['platform']);
        echo '<a href="' . htmlspecialchars($link['url']) . '" target="_blank" rel="noopener"'
            . ' class="flex items-center gap-1.5 ' . $pad . ' rounded-full font-bold text-white hover:opacity-90 transition-opacity"'
            . ' style="background-color:' . htmlspecialchars($style['color']) . '">'
            . '<span class="material-symbols-outlined text-[16px]">' . $style['icon'] . '</span>'
            . htmlspecialchars($link['platform'])
            . '</a>';
    }
    echo '</div>';
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function unique_product_slug(string $name): string
{
    $base = slugify($name) ?: 'item';
    $slug = $base;
    $suffix = 2;
    $stmt = db()->prepare('SELECT id FROM products WHERE slug = ?');
    while (true) {
        $stmt->execute([$slug]);
        if (!$stmt->fetch()) return $slug;
        $slug = $base . '-' . $suffix++;
    }
}

function unique_product_sku(string $hint = ''): string
{
    $base = $hint !== '' ? strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper($hint))) : '';
    $stmt = db()->prepare('SELECT id FROM products WHERE sku = ?');
    if ($base !== '') {
        $stmt->execute([$base]);
        if (!$stmt->fetch()) return $base;
    }
    do {
        $sku = 'RCPT-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt->execute([$sku]);
    } while ($stmt->fetch());
    return $sku;
}

/**
 * Calls the OCR.space free API to extract raw text from a receipt photo.
 * Returns the extracted text, or throws with a human-readable message.
 */
function ocr_extract_text(string $filePath, string $mimeType, string $extension): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP curl extension is not available on this server.');
    }

    $extension = strtolower($extension);
    $fileTypeMap = ['jpg' => 'JPG', 'jpeg' => 'JPG', 'png' => 'PNG', 'webp' => 'PNG', 'pdf' => 'PDF'];
    $fileType = $fileTypeMap[$extension] ?? 'JPG';

    $ch = curl_init('https://api.ocr.space/parse/image');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => [
            'apikey' => OCR_SPACE_API_KEY,
            'language' => 'eng',
            'OCREngine' => '2',
            'isTable' => 'true',
            'scale' => 'true',
            'filetype' => $fileType,
            'file' => new CURLFile($filePath, $mimeType, 'receipt.' . $extension),
        ],
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Could not reach the OCR service: ' . $curlError);
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('OCR service returned an unexpected response.');
    }
    if (!empty($data['IsErroredOnProcessing'])) {
        $msg = is_array($data['ErrorMessage'] ?? null) ? implode(' ', $data['ErrorMessage']) : ($data['ErrorMessage'] ?? 'Unknown OCR error.');
        throw new RuntimeException($msg);
    }

    $text = '';
    foreach ($data['ParsedResults'] ?? [] as $result) {
        $text .= ($result['ParsedText'] ?? '') . "\n";
    }
    if (trim($text) === '') {
        throw new RuntimeException('No text could be read from that image. Try a clearer, well-lit photo.');
    }
    return $text;
}

/**
 * Best-effort line-by-line parser for OCR'd (or pasted) receipt text.
 * Every result is meant to be reviewed and corrected by hand before saving —
 * receipt layouts vary too much for this to be reliable on its own.
 */
function parse_receipt_lines(string $text): array
{
    $skipWords = '/^(total|subtotal|sub total|tax|vat|cash|change|balance|amount due|thank you|receipt|invoice|date|qty|item|description|customer|cashier|served by|payment|paid|visa|mpesa|m-pesa)\b/i';

    $items = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '' || preg_match($skipWords, $line)) {
            continue;
        }

        // Pull an explicit "x3" / "X 3" / "*3" quantity marker out first, wherever
        // it sits, so it doesn't get mistaken for a price further down.
        $qty = 1;
        $hasExplicitQty = false;
        if (preg_match('/[xX*]\s*(\d+)\b/', $line, $qtyMatch)) {
            $qty = max(1, (int) $qtyMatch[1]);
            $hasExplicitQty = true;
            $line = trim(str_replace($qtyMatch[0], ' ', $line));
        }

        if (!preg_match_all('/\d[\d,]*\.?\d*/', $line, $matches)) {
            continue;
        }
        $numbers = $matches[0];
        $positions = [];
        $offset = 0;
        foreach ($numbers as $n) {
            $pos = strpos($line, $n, $offset);
            $positions[] = $pos;
            $offset = $pos + strlen($n);
        }

        $cost = (float) str_replace(',', '', end($numbers));
        if ($cost <= 0) {
            continue;
        }

        $startsWithNumber = $positions[0] === 0;

        if ($startsWithNumber && count($numbers) >= 2) {
            // "2  Denim Jacket  1200" — leading number is the quantity.
            if (!$hasExplicitQty) {
                $qty = max(1, (int) str_replace(',', '', $numbers[0]));
            }
            $nameStart = $positions[0] + strlen($numbers[0]);
            $nameEnd = $positions[count($positions) - 1];
            $name = substr($line, $nameStart, $nameEnd - $nameStart);
        } elseif (!$hasExplicitQty && count($numbers) >= 2) {
            // "Cotton T-Shirt  12  450.00" — a plain integer just before the
            // price is very likely the quantity, not part of the name.
            $secondLast = $numbers[count($numbers) - 2];
            $secondLastPos = $positions[count($positions) - 2];
            if (strpos($secondLast, '.') === false && (int) str_replace(',', '', $secondLast) <= 999) {
                $qty = max(1, (int) str_replace(',', '', $secondLast));
                $name = substr($line, 0, $secondLastPos);
            } else {
                $nameEnd = $positions[count($positions) - 1];
                $name = substr($line, 0, $nameEnd);
            }
        } else {
            $nameEnd = $positions[count($positions) - 1];
            $name = substr($line, 0, $nameEnd);
        }

        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim(preg_replace('/^[\sxX@*\-:=.]+|[\sxX@*\-:=.]+$/', '', $name));
        if ($name === '' || mb_strlen($name) < 2) {
            continue;
        }

        $items[] = ['name' => $name, 'qty' => $qty, 'cost_price' => $cost];
    }

    return $items;
}

/**
 * Parses a supplier CSV export. Expects a header row; column names are
 * matched case-insensitively against common aliases.
 */
function parse_receipt_csv(string $filePath): array
{
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new RuntimeException('Could not read the uploaded CSV.');
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        throw new RuntimeException('The CSV file appears to be empty.');
    }
    $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

    $nameAliases = ['name', 'product', 'product name', 'item', 'description'];
    $qtyAliases = ['qty', 'quantity', 'units'];
    $costAliases = ['cost', 'cost price', 'cost_price', 'unit cost', 'unit price', 'price'];

    $nameIdx = null; $qtyIdx = null; $costIdx = null;
    foreach ($header as $i => $h) {
        if ($nameIdx === null && in_array($h, $nameAliases, true)) $nameIdx = $i;
        if ($qtyIdx === null && in_array($h, $qtyAliases, true)) $qtyIdx = $i;
        if ($costIdx === null && in_array($h, $costAliases, true)) $costIdx = $i;
    }

    if ($nameIdx === null || $costIdx === null) {
        fclose($handle);
        throw new RuntimeException('CSV must have a "name" column and a "cost" (or "price") column. Found columns: ' . implode(', ', $header));
    }

    $items = [];
    while (($row = fgetcsv($handle)) !== false) {
        $name = trim((string) ($row[$nameIdx] ?? ''));
        if ($name === '') continue;
        $qty = $qtyIdx !== null ? max(1, (int) ($row[$qtyIdx] ?? 1)) : 1;
        $cost = (float) str_replace(',', '', (string) ($row[$costIdx] ?? 0));
        if ($cost <= 0) continue;
        $items[] = ['name' => $name, 'qty' => $qty, 'cost_price' => $cost];
    }
    fclose($handle);

    return $items;
}

function get_low_stock_items(): array
{
    return db()->query('SELECT p.name, p.reorder_level, COALESCE(SUM(pv.stock),0) AS stock
        FROM products p JOIN product_variants pv ON pv.product_id = p.id
        GROUP BY p.id, p.name, p.reorder_level
        HAVING stock <= p.reorder_level
        ORDER BY stock ASC')->fetchAll();
}

function low_stock_whatsapp_link(array $lowStockItems): ?string
{
    if (empty($lowStockItems)) {
        return null;
    }
    $lines = ['Low stock alert — ' . BUSINESS_NAME . ' (' . date('j M Y') . ')', ''];
    foreach ($lowStockItems as $item) {
        $lines[] = '- ' . $item['name'] . ': ' . $item['stock'] . ' left (reorder at ' . $item['reorder_level'] . ')';
    }
    return 'https://wa.me/?text=' . rawurlencode(implode("\n", $lines));
}

function generate_order_number(): string
{
    return 'TNK2-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
}

function stock_status(int $stock, int $reorderLevel): array
{
    if ($stock <= 0) {
        return ['label' => 'Out of Stock', 'color' => 'danger-red', 'pct' => 0];
    }
    if ($stock <= $reorderLevel) {
        return ['label' => $stock . ' units remaining', 'color' => 'danger-red', 'pct' => max(8, min(100, (int) ($stock / max($reorderLevel, 1) * 40)))];
    }
    if ($stock <= $reorderLevel * 3) {
        return ['label' => $stock . ' units remaining', 'color' => 'warning-amber', 'pct' => min(100, (int) ($stock / ($reorderLevel * 3) * 70))];
    }
    return ['label' => $stock . ' units remaining', 'color' => 'secondary', 'pct' => 100];
}
