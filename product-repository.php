<?php
declare(strict_types=1);

function extra_store_normalize_storefront(string $value): string
{
    $value = strtolower(trim($value));

    if ($value === 'light') {
        return 'light';
    }

    if ($value === 'iron') {
        return 'iron';
    }

    return 'extra';
}

function extra_store_product_defaults(): array
{
    return [
        [
            'id' => 'umbrella-red',
            'name' => 'Classic Red Auto Umbrella',
            'price' => 24000,
            'color' => 'Red',
            'category' => 'Travel Essential',
            'image_primary' => 'assets/red-product-clean.png',
            'images' => ['assets/red-product-clean.png', 'assets/pink.jpg'],
            'description' => 'A compact automatic umbrella with windproof protection and a polished red finish for everyday carry.',
            'storefront' => 'extra',
        ],
        [
            'id' => 'umbrella-green',
            'name' => 'Green Windproof Umbrella',
            'price' => 24000,
            'color' => 'Green',
            'category' => 'Travel Essential',
            'image_primary' => 'assets/green.jpg',
            'images' => ['assets/green.jpg', 'assets/imgi_366_Hab9faf1e4c674af48cef4986f2494d0e7.jpg'],
            'description' => 'A lightweight umbrella built for rain-ready protection and easy storage.',
            'storefront' => 'extra',
        ],
        [
            'id' => 'umbrella-blue',
            'name' => 'Blue Compact Auto Umbrella',
            'price' => 24000,
            'color' => 'Blue',
            'category' => 'Travel Essential',
            'image_primary' => 'assets/blue.jpg',
            'images' => ['assets/blue.jpg', 'assets/imgi_366_Hab9faf1e4c674af48cef4986f2494d0e7.jpg', 'assets/tfgh.png'],
            'description' => 'A stylish compact umbrella with automatic open and close convenience.',
            'storefront' => 'extra',
        ],
        [
            'id' => 'umbrella-pink',
            'name' => 'Pink Compact Umbrella',
            'price' => 24000,
            'color' => 'Pink',
            'category' => 'Travel Essential',
            'image_primary' => 'assets/WhatsApp Image 2026-08-13 at 18.35.25.jpeg',
            'images' => ['assets/WhatsApp Image 2026-08-13 at 18.35.25.jpeg'],
            'description' => 'A bright, practical umbrella with a soft finish and travel-friendly format.',
            'storefront' => 'extra',
        ],
        [
            'id' => 'solar-clip-lamp',
            'name' => 'Solar Rechargeable Clip-On Desk Lamp',
            'price' => 19500,
            'color' => 'White',
            'category' => 'Reading & Study',
            'image_primary' => 'assets/imgi_1_1.jpg',
            'images' => ['assets/imgi_1_1.jpg', 'assets/imgi_4_4.jpg', 'assets/imgi_5_5.jpeg'],
            'description' => 'A rechargeable clip-on desk lamp with adjustable neck, solar charging, and soft eye-friendly lighting.',
            'storefront' => 'light',
        ],
        [
            'id' => 'iron-clip-lamp',
            'name' => 'Iron Rechargeable Clip-On Desk Lamp',
            'price' => 19500,
            'color' => 'Black',
            'category' => 'Reading & Study',
            'image_primary' => 'assets/iron-1.jpg',
            'images' => ['assets/iron-1.jpg', 'assets/iron-2.jpg', 'assets/iron-3.jpeg'],
            'description' => 'A rechargeable clip-on desk lamp with a sturdy iron finish and soft eye-friendly lighting.',
            'storefront' => 'iron',
        ],
    ];
}

function extra_store_ensure_products_schema(PDO $pdo): void
{
    $columnExists = false;

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'storefront'");
        $columnExists = (bool) ($stmt && $stmt->fetch());
    } catch (Throwable $error) {
        $columnExists = false;
    }

    if (!$columnExists) {
        $pdo->exec("ALTER TABLE products ADD COLUMN storefront VARCHAR(20) NOT NULL DEFAULT 'extra' AFTER description");
    }

    $pdo->exec("UPDATE products SET storefront = 'extra' WHERE storefront IS NULL OR storefront = ''");
}

function extra_store_seed_default_products(PDO $pdo): void
{
    $existingCount = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($existingCount > 0) {
        return;
    }

    $check = $pdo->prepare('SELECT COUNT(*) FROM products WHERE id = :id');
    $insert = $pdo->prepare(
        'INSERT INTO products (
            id,
            name,
            price,
            color,
            category,
            image_primary,
            images_json,
            description,
            storefront
        ) VALUES (
            :id,
            :name,
            :price,
            :color,
            :category,
            :image_primary,
            :images_json,
            :description,
            :storefront
        )'
    );

    foreach (extra_store_product_defaults() as $product) {
        $check->execute(['id' => $product['id']]);
        if ((int) $check->fetchColumn() > 0) {
            continue;
        }

        $insert->execute([
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'color' => $product['color'],
            'category' => $product['category'],
            'image_primary' => $product['image_primary'],
            'images_json' => json_encode($product['images'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
            'description' => $product['description'],
            'storefront' => $product['storefront'],
        ]);
    }
}

function extra_store_bootstrap_catalog(PDO $pdo): void
{
    extra_store_ensure_products_schema($pdo);
    extra_store_seed_default_products($pdo);
}

function extra_store_normalize_image_list($value, string $primaryImage = ''): array
{
    $items = [];

    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $value = $decoded;
        } else {
            $value = preg_split('/[\r\n,]+/', $value) ?: [];
        }
    }

    if (is_array($value)) {
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $items[] = $item;
            }
        }
    }

    $primaryImage = trim($primaryImage);
    if ($primaryImage !== '') {
        array_unshift($items, $primaryImage);
    }

    $items = array_values(array_unique(array_filter($items, static fn ($item) => trim((string) $item) !== '')));

    if (!$items && $primaryImage !== '') {
        $items[] = $primaryImage;
    }

    return $items;
}

function extra_store_product_from_row(array $row): array
{
    $primaryImage = trim((string) ($row['image_primary'] ?? ''));
    $images = extra_store_normalize_image_list($row['images_json'] ?? null, $primaryImage);
    $storefront = extra_store_normalize_storefront((string) ($row['storefront'] ?? ''));

    if ($storefront === 'extra' && str_starts_with((string) ($row['id'] ?? ''), 'light-')) {
        $storefront = 'light';
    } elseif ($storefront === 'extra' && str_starts_with((string) ($row['id'] ?? ''), 'iron-')) {
        $storefront = 'iron';
    }

    return [
        'id' => (string) ($row['id'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'price' => (int) ($row['price'] ?? 0),
        'color' => (string) ($row['color'] ?? ''),
        'category' => (string) ($row['category'] ?? ''),
        'image_primary' => $primaryImage !== '' ? $primaryImage : ($images[0] ?? ''),
        'images' => $images,
        'description' => (string) ($row['description'] ?? ''),
        'storefront' => $storefront,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function extra_store_fetch_products(PDO $pdo, ?string $storefront = null): array
{
    $storefront = $storefront !== null ? strtolower(trim($storefront)) : null;

    if ($storefront === null || $storefront === '' || $storefront === 'all') {
        $stmt = $pdo->query('SELECT * FROM products ORDER BY created_at ASC, id ASC');
        $rows = $stmt ? $stmt->fetchAll() : [];
    } else {
        $storefront = extra_store_normalize_storefront($storefront);
        $stmt = $pdo->prepare('SELECT * FROM products WHERE storefront = :storefront ORDER BY created_at ASC, id ASC');
        $stmt->execute(['storefront' => $storefront]);
        $rows = $stmt->fetchAll();
    }

    return array_map('extra_store_product_from_row', is_array($rows) ? $rows : []);
}

function extra_store_fetch_product(PDO $pdo, string $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return extra_store_product_from_row($row);
}

function extra_store_prepare_images_json(string $primaryImage, string $imageListText): string
{
    $images = extra_store_normalize_image_list($imageListText, $primaryImage);
    return json_encode($images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
}

function extra_store_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'product';
}

function extra_store_generate_product_id(PDO $pdo, string $name, string $preferredId = ''): string
{
    $base = extra_store_slugify($preferredId !== '' ? $preferredId : $name);
    $candidate = $base;
    $suffix = 2;

    $check = $pdo->prepare('SELECT COUNT(*) FROM products WHERE id = :id');
    while (true) {
        $check->execute(['id' => $candidate]);
        if ((int) $check->fetchColumn() === 0) {
            return $candidate;
        }

        $candidate = $base . '-' . $suffix;
        $suffix++;
    }
}

function extra_store_update_product(PDO $pdo, array $input): array
{
    $id = trim((string) ($input['id'] ?? ''));
    if ($id === '') {
        throw new InvalidArgumentException('Missing product id.');
    }

    $name = trim((string) ($input['name'] ?? ''));
    $price = (int) ($input['price'] ?? 0);
    $color = trim((string) ($input['color'] ?? ''));
    $category = trim((string) ($input['category'] ?? ''));
    $imagePrimary = trim((string) ($input['image_primary'] ?? ''));
    $imagesText = trim((string) ($input['images_text'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $storefront = extra_store_normalize_storefront((string) ($input['storefront'] ?? 'extra'));

    if ($name === '' || $color === '' || $category === '' || $imagePrimary === '' || $description === '') {
        throw new InvalidArgumentException('Please fill in all required product fields.');
    }

    if ($price < 0) {
        throw new InvalidArgumentException('Price must be a positive number.');
    }

    $imagesJson = extra_store_prepare_images_json($imagePrimary, $imagesText);

    $stmt = $pdo->prepare(
        'UPDATE products
         SET name = :name,
             price = :price,
             color = :color,
             category = :category,
             image_primary = :image_primary,
             images_json = :images_json,
             description = :description,
             storefront = :storefront
         WHERE id = :id'
    );

    $stmt->execute([
        'id' => $id,
        'name' => $name,
        'price' => $price,
        'color' => $color,
        'category' => $category,
        'image_primary' => $imagePrimary,
        'images_json' => $imagesJson,
        'description' => $description,
        'storefront' => $storefront,
    ]);

    return extra_store_fetch_product($pdo, $id) ?? [];
}

function extra_store_create_product(PDO $pdo, array $input): array
{
    $preferredId = trim((string) ($input['id'] ?? ''));
    $name = trim((string) ($input['name'] ?? ''));
    $price = (int) ($input['price'] ?? 0);
    $color = trim((string) ($input['color'] ?? ''));
    $category = trim((string) ($input['category'] ?? ''));
    $imagePrimary = trim((string) ($input['image_primary'] ?? ''));
    $imagesText = trim((string) ($input['images_text'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $storefront = extra_store_normalize_storefront((string) ($input['storefront'] ?? 'extra'));

    if ($name === '' || $color === '' || $category === '' || $imagePrimary === '' || $description === '') {
        throw new InvalidArgumentException('Please fill in all required product fields.');
    }

    if ($price < 0) {
        throw new InvalidArgumentException('Price must be a positive number.');
    }

    $id = $preferredId !== '' ? extra_store_slugify($preferredId) : extra_store_generate_product_id($pdo, $name);
    $imagesJson = extra_store_prepare_images_json($imagePrimary, $imagesText);

    $stmt = $pdo->prepare(
        'INSERT INTO products (
            id,
            name,
            price,
            color,
            category,
            image_primary,
            images_json,
            description,
            storefront
        ) VALUES (
            :id,
            :name,
            :price,
            :color,
            :category,
            :image_primary,
            :images_json,
            :description,
            :storefront
        )'
    );

    $stmt->execute([
        'id' => $id,
        'name' => $name,
        'price' => $price,
        'color' => $color,
        'category' => $category,
        'image_primary' => $imagePrimary,
        'images_json' => $imagesJson,
        'description' => $description,
        'storefront' => $storefront,
    ]);

    return extra_store_fetch_product($pdo, $id) ?? [];
}

function extra_store_delete_product(PDO $pdo, string $id): void
{
    $id = trim($id);
    if ($id === '') {
        throw new InvalidArgumentException('Missing product id.');
    }

    $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
    $stmt->execute(['id' => $id]);
}
