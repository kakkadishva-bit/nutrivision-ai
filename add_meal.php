<?php
session_start();
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

include 'db.php';
include 'ai_bridge.php';

$user_id = $_SESSION['user_id'];
$food_detected = null;
$detection_error = null;
$meal_added = false;
$meal_error = '';
$nutrition_row = null;
$is_custom = false;

// Handle food image upload
if(isset($_POST['upload_image'])) {
    if(isset($_FILES['food_image']) && $_FILES['food_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = time() . '_' . basename($_FILES['food_image']['name']);
        $target_path = $upload_dir . $file_name;
        if(move_uploaded_file($_FILES['food_image']['tmp_name'], $target_path)) {
            global $ai;
            $result = $ai->detectFood($target_path);
            if(isset($result['success']) && $result['success']) {
                $food_detected = $result['best_match'];
            } else {
                $detection_error = $result['error'] ?? "Food unclear. Select manually.";
            }
        }
    } else {
        $detection_error = "Please select an image.";
    }
}

// Handle manual meal add
if(isset($_POST['add_meal'])) {
    $food = trim($_POST['food'] ?? '');
    $custom_food = trim($_POST['custom_food'] ?? '');
    $quantity = (float)($_POST['quantity'] ?? 1);
    
    if ($food === 'Other' && !empty($custom_food)) {
        $food = $custom_food;
        $is_custom = true;
    }
    
    if (empty($food)) {
        $meal_error = "Please select or enter a food item.";
    } elseif ($quantity <= 0) {
        $meal_error = "Quantity must be greater than 0.";
    } else {
        $food_safe = mysqli_real_escape_string($conn, $food);
        $query = "INSERT INTO meals (user_id, food_name, quantity) VALUES ('$user_id', '$food_safe', '$quantity')";
        if(mysqli_query($conn, $query)) {
            $meal_added = true;
            // Try to get nutrition from database
            $nr = mysqli_query($conn, "SELECT * FROM food_items WHERE food_name='$food_safe'");
            if ($nr && mysqli_num_rows($nr) > 0) {
                $nutrition_row = mysqli_fetch_assoc($nr);
                $is_custom = false;
            } else {
                $is_custom = true;
            }
        } else {
            $meal_error = "Database error: " . mysqli_error($conn);
        }
    }
}

// Get all food items
$food_result = mysqli_query($conn, "SELECT * FROM food_items ORDER BY food_name");

if(!$nutrition_row && $food_detected) {
    $nr = mysqli_query($conn, "SELECT * FROM food_items WHERE food_name LIKE '%" . mysqli_real_escape_string($conn, $food_detected) . "%'");
    if ($nr) $nutrition_row = mysqli_fetch_assoc($nr);
}
?>
<!DOCTYPE html>
<html>
<head><title>Add Meal - NutriVision</title><link rel="stylesheet" href="style.css">
<style>
.upload-area { border:2px dashed #ccc; border-radius:12px; padding:30px; text-align:center; cursor:pointer; transition:.3s; background:#f9f9f9; margin-bottom:20px; }
.upload-area:hover { border-color:var(--primary); background:#f0fff0; }
.upload-area img { max-width:200px; max-height:200px; margin-top:10px; border-radius:8px; }
.detection-result { padding:15px; border-radius:8px; margin:10px 0; }
.detection-success { background:#d4edda; border:1px solid #c3e6cb; color:#155724; }
.detection-error { background:#fff3cd; border:1px solid #ffeeba; color:#856404; }
body.dark-mode .upload-area { background:#1a1a2e; border-color:#444; }
body.dark-mode .detection-success { background:#1e3a1e; color:#8bc34a; }
body.dark-mode .detection-error { background:#3a2e1e; color:#ffc107; }
.cat-btn { padding:5px 12px; border:1px solid #ddd; border-radius:20px; background:white; cursor:pointer; font-size:0.8rem; display:inline-block; margin:2px; }
.cat-btn.active { background:var(--primary); color:white; border-color:var(--primary); }
body.dark-mode .cat-btn { background:#1a1a3e; color:#eee; border-color:#444; }
body.dark-mode .cat-btn.active { background:var(--primary); color:white; }
</style></head>
<body>
<div class="navbar">
    <h2>🍽️ NutriVision</h2>
    <div class="nav-links">
        <span class="dark-toggle" onclick="toggleDarkMode()">🌙 Dark</span>
        <a href="dashboard.php" class="btn">📊 Dashboard</a>
    </div>
</div>

<div class="container">
    <h1>🍽️ Add Meal</h1>
    
    <?php if($meal_added): ?>
    <div class="alert alert-success">✅ Meal added successfully! <a href="dashboard.php">View Dashboard</a></div>
    <?php endif; ?>
    <?php if($meal_error): ?>
    <div class="alert alert-danger">❌ <?php echo $meal_error; ?></div>
    <?php endif; ?>

    <!-- Upload Photo -->
    <div class="card" style="margin:20px 0;">
        <h2>📸 Upload Photo</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="upload-area" id="uploadArea" onclick="document.getElementById('food_image').click()">
                <p style="font-size:48px; margin:0;">🍽️</p>
                <p><strong>Click to upload</strong></p>
                <img id="preview" style="display:none;">
                <input type="file" id="food_image" name="food_image" accept="image/*" style="display:none;" onchange="preview(event)">
            </div>
            <button type="submit" name="upload_image" class="btn btn-accent">🔍 Detect Food</button>
        </form>
        <?php if($food_detected): ?><div class="detection-result detection-success">✅ Detected: <?php echo htmlspecialchars($food_detected); ?></div><?php endif; ?>
        <?php if($detection_error): ?><div class="detection-result detection-error">⚠️ <?php echo htmlspecialchars($detection_error); ?></div><?php endif; ?>
    </div>

    <!-- Manual Entry -->
    <form method="POST">
        <h2>📝 Add Food</h2>
        
        <label>Filter</label>
        <div style="margin-bottom:10px;">
            <span class="cat-btn active" onclick="filter('all',this)">🍽️ All</span>
            <span class="cat-btn" onclick="filter('Grains',this)">🌾 Grains</span>
            <span class="cat-btn" onclick="filter('Pulse',this)">🥗 Lentils</span>
            <span class="cat-btn" onclick="filter('Vegetable',this)">🥬 Veg</span>
            <span class="cat-btn" onclick="filter('Protein',this)">💪 Protein</span>
            <span class="cat-btn" onclick="filter('Fruit',this)">🍎 Fruits</span>
            <span class="cat-btn" onclick="filter('Gujarati',this)">🫓 Gujarati</span>
            <span class="cat-btn" onclick="filter('South Indian',this)">🫓 South</span>
            <span class="cat-btn" onclick="filter('Breakfast',this)">🌅 Breakfast</span>
            <span class="cat-btn" onclick="filter('Dairy',this)">🥛 Dairy</span>
            <span class="cat-btn" onclick="filter('Nuts',this)">🥜 Nuts</span>
        </div>

        <label>Select Food</label>
        <select name="food" id="foodSelect" required>
            <option value="">-- Select --</option>
            <?php 
            if($food_result && mysqli_num_rows($food_result) > 0):
                while($food = mysqli_fetch_assoc($food_result)):
                    $sel = ($food_detected && stripos($food['food_name'], $food_detected) !== false) ? 'selected' : '';
            ?>
            <option value="<?php echo htmlspecialchars($food['food_name']); ?>" <?php echo $sel; ?> data-cat="<?php echo $food['category'] ?? ''; ?>">
                <?php echo htmlspecialchars($food['food_name']); ?> (<?php echo $food['calories']; ?> kcal)
            </option>
            <?php endwhile; endif; ?>
            <option value="Other">✏️ Other (type manually)</option>
        </select>

        <div id="customDiv" style="display:none;">
            <label>Enter Food Name</label>
            <input type="text" name="custom_food" placeholder="e.g. My homemade dish">
        </div>

        <label>Quantity (servings)</label>
        <input type="number" name="quantity" value="1" min="0.5" step="0.5" required>

        <button type="submit" name="add_meal" class="btn btn-lg">✅ Add Meal</button>
    </form>

    <?php if($meal_added && $is_custom): ?>
    <div class="card">
        <h2>📊 Nutrition</h2>
        <div class="alert alert-info">
            <strong>✏️ Custom Food Added:</strong> "<?php echo htmlspecialchars($_POST['custom_food'] ?? $_POST['food']); ?>"<br>
            <p style="margin-top:8px;">ℹ️ Nutrition info not available in database. Please enter the values manually or select from the list next time.</p>
            <p>💡 <strong>Suggestion:</strong> For accurate tracking, select a similar food from the dropdown list above.</p>
        </div>
    </div>
    <?php endif; ?>

    <?php if($nutrition_row && !$is_custom): ?>
    <div class="card">
        <h2>📊 Nutrition (per serving)</h2>
        <div class="cards" style="grid-template-columns: repeat(auto-fit, minmax(130px,1fr));">
            <div class="card"><h3>🔥 Calories</h3><h1><?php echo $nutrition_row['calories']; ?> kcal</h1></div>
            <div class="card"><h3>💪 Protein</h3><h1><?php echo $nutrition_row['protein']; ?>g</h1></div>
            <div class="card"><h3>🌾 Carbs</h3><h1><?php echo $nutrition_row['carbs']; ?>g</h1></div>
            <div class="card"><h3>🥑 Fat</h3><h1><?php echo $nutrition_row['fat']; ?>g</h1></div>
            <div class="card"><h3>🌿 Fiber</h3><h1><?php echo $nutrition_row['fiber']; ?>g</h1></div>
            <div class="card"><h3>🍬 Sugar</h3><h1><?php echo $nutrition_row['sugar']; ?>g</h1></div>
            <div class="card"><h3>🫓 Cholest.</h3><h1><?php echo $nutrition_row['cholesterol']; ?>mg</h1></div>
        </div>
        <small>Vitamins: <?php echo $nutrition_row['vitamins']; ?> | Minerals: <?php echo $nutrition_row['minerals']; ?></small>
    </div>
    <?php endif; ?>
</div>

<script>
function preview(e) {
    const r = new FileReader();
    r.onload = function() { document.getElementById('preview').src = r.result; document.getElementById('preview').style.display = 'block'; }
    if(e.target.files[0]) r.readAsDataURL(e.target.files[0]);
}
document.getElementById('uploadArea').ondragover = e => { e.preventDefault(); e.target.classList.add('dragover'); };
document.getElementById('uploadArea').ondragleave = e => { e.target.classList.remove('dragover'); };
document.getElementById('uploadArea').ondrop = e => {
    e.preventDefault(); e.target.classList.remove('dragover');
    if(e.dataTransfer.files[0]) { document.getElementById('food_image').files = e.dataTransfer.files; preview(e); }
};
function filter(cat, btn) {
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('#foodSelect option').forEach(o => {
        if(o.dataset.cat) o.style.display = (cat==='all' || o.dataset.cat.includes(cat)) ? '' : 'none';
    });
}
document.getElementById('foodSelect').onchange = function() {
    document.getElementById('customDiv').style.display = this.value === 'Other' ? 'block' : 'none';
};
function toggleDarkMode() { document.body.classList.toggle('dark-mode'); }
</script>
</body>
</html>