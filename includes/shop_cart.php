<?php
/** Session-based cart for the public customer shop (separate namespace from admin session data) */

function shop_cart(): array
{
    if (!isset($_SESSION['shop_cart'])) {
        $_SESSION['shop_cart'] = [];
    }
    return $_SESSION['shop_cart'];
}

function shop_cart_add(int $variantId, int $qty): void
{
    if (!isset($_SESSION['shop_cart'])) {
        $_SESSION['shop_cart'] = [];
    }
    $key = (string) $variantId;
    $_SESSION['shop_cart'][$key] = ($_SESSION['shop_cart'][$key] ?? 0) + $qty;
}

function shop_cart_update(int $variantId, int $qty): void
{
    $key = (string) $variantId;
    if ($qty <= 0) {
        unset($_SESSION['shop_cart'][$key]);
    } else {
        $_SESSION['shop_cart'][$key] = $qty;
    }
}

function shop_cart_remove(int $variantId): void
{
    unset($_SESSION['shop_cart'][(string) $variantId]);
}

function shop_cart_clear(): void
{
    $_SESSION['shop_cart'] = [];
}

function shop_cart_count(): int
{
    return array_sum(shop_cart());
}

function shop_cart_items(): array
{
    $items = [];
    foreach (shop_cart() as $variantId => $qty) {
        $variant = get_variant((int) $variantId);
        if ($variant) {
            $variant['qty'] = min($qty, $variant['stock']);
            $variant['line_total'] = $variant['effective_price'] * $variant['qty'];
            $items[] = $variant;
        }
    }
    return $items;
}

function shop_cart_subtotal(): float
{
    $total = 0;
    foreach (shop_cart_items() as $item) {
        $total += $item['line_total'];
    }
    return $total;
}
