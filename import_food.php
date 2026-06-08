<?php
// import_food.php
// This script reads food_data.json and inserts the records into the food_items table.

include 'db.php';

$jsonFile = 'food_data.json';
if (!file_exists($jsonFile)) {
    die("Error: $jsonFile not found.");
}

$data = file_get_contents($jsonFile);
$foods = json_decode($data, true);
if ($foods === null) {
    die("Error: Failed to decode JSON.");
}

$inserted = 0;
foreach ($foods as $food) {
    $stmt = $conn->prepare("
        INSERT INTO food_items
        (food_name, calories, protein, carbs, fat, fiber, sugar, cholesterol, vitamins, minerals, category, image_url)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            calories = VALUES(calories),
            protein = VALUES(protein),
            carbs = VALUES(carbs),
            fat = VALUES(fat),
            fiber = VALUES(fiber),
            sugar = VALUES(sugar),
            cholesterol = VALUES(cholesterol),
            vitamins = VALUES(vitamins),
            minerals = VALUES(minerals),
            category = VALUES(category),
            image_url = VALUES(image_url)
    ");

    $stmt->bind_param(
        "siddddddssss",
        $food['food_name'],
        $food['calories'],
        $food['protein'],
        $food['carbs'],
        $food['fat'],
        $food['fiber'],
        $food['sugar'],
        $food['cholesterol'],
        $food['vitamins'],
        $food['minerals'],
        $food['category'],
        $food['image_url']
    );

    if ($stmt->execute()) {
        $inserted++;
    } else {
        echo "Failed to insert {$food['food_name']}: " . $stmt->error . PHP_EOL;
    }
    $stmt->close();
}

echo "Import completed. $inserted records inserted/updated." . PHP_EOL;
?>