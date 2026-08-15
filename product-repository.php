<?php
declare(strict_types=1);

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

    return [
        'id' => (string) ($row['id'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'price' => (int) ($row['price'] ?? 0),
        'color' => (string) ($row['color'] ?? ''),
        'category' => (string) ($row['category'] ?? ''),
        'image_primary' => $primaryImage !== '' ? $primaryImage : ($images[0] ?? ''),
        'images' => $images,
        'description' => (string) ($row['description'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function extra_store_fetch_products(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM products ORDER BY created_at ASC, id ASC');
    $rows = $stmt ? $stmt->fetchAll() : [];

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
             description = :description
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
            description
        ) VALUES (
            :id,
            :name,
            :price,
            :color,
            :category,
            :image_primary,
            :images_json,
            :description
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
