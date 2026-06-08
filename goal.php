<?php
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';

$user_id = $_SESSION['user_id'];
$goal_saved = false;

// Handle goal saving
if(isset($_POST['save_goal'])) {
    $goal_type = mysqli_real_escape_string($conn, $_POST['goal_type']);
    $daily_calorie_goal = (int)$_POST['daily_calorie_goal'];
    $target_protein = (float)$_POST['target_protein'];
    $target_carbs = (float)$_POST['target_carbs'];
    $target_fat = (float)$_POST['target_fat'];
    
    // Update users table
    mysqli_query($conn, "UPDATE users SET daily_calorie_goal='$daily_calorie_goal', goal='$goal_type' WHERE id='$user_id'");
    
    // Check if goals row exists
    $check = mysqli_query($conn, "SELECT id FROM goals WHERE user_id = $user_id ORDER BY id DESC LIMIT 1");
    if ($check && mysqli_num_rows($check) > 0) {
        $g = mysqli_fetch_assoc($check);
        $goal_id = $g['id'];
        $g_query = "UPDATE goals SET target_calories='$daily_calorie_goal', target_protein='$target_protein', target_carbs='$target_carbs', target_fat='$target_fat', start_date=CURDATE() WHERE id=$goal_id";
    } else {
        $g_query = "INSERT INTO goals (user_id, target_calories, target_protein, target_carbs, target_fat, start_date) VALUES ('$user_id', '$daily_calorie_goal', '$target_protein', '$target_carbs', '$target_fat', CURDATE())";
    }
    mysqli_query($conn, $g_query);
    
    $goal_saved = true;
}

// Get user data
$user_query = "SELECT * FROM users WHERE id='$user_id'";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

// Get current goal
$goal_query = "SELECT * FROM goals WHERE user_id = $user_id ORDER BY id DESC LIMIT 1";
$goal_result = mysqli_query($conn, $goal_query);
$current_goal = mysqli_fetch_assoc($goal_result);

// Get today's actual intake
$today_query = "SELECT 
    COALESCE(SUM(food_items.calories * meals.quantity), 0) AS today_cal,
    COALESCE(SUM(food_items.protein * meals.quantity), 0) AS today_prot,
    COALESCE(SUM(food_items.carbs * meals.quantity), 0) AS today_carbs,
    COALESCE(SUM(food_items.fat * meals.quantity), 0) AS today_fat
FROM meals 
JOIN food_items ON meals.food_name = food_items.food_name 
WHERE meals.user_id='$user_id' AND DATE(meals.meal_time) = CURDATE()";
$today_result = mysqli_query($conn, $today_query);
$today = mysqli_fetch_assoc($today_result);
$today_cal = $today['today_cal'] ?? 0;
$today_prot = $today['today_prot'] ?? 0;
$today_carbs = $today['today_carbs'] ?? 0;
$today_fat = $today['today_fat'] ?? 0;

// Calculate BMR
$weight = (float)($user['weight'] ?? 70);
$height = (float)($user['height'] ?? 170);
$age = (int)($user['age'] ?? 30);
$gender = $user['gender'] ?? 'Male';

if($gender == 'Male') {
    $bmr = 10 * $weight + 6.25 * $height - 5 * $age + 5;
} else {
    $bmr = 10 * $weight + 6.25 * $height - 5 * $age - 161;
}

$activity_mult = $user['activity_level'] == 'Advanced' ? 1.725 : ($user['activity_level'] == 'Intermediate' ? 1.55 : 1.375);
$maintenance_calories = round($bmr * $activity_mult);
?>
<!DOCTYPE html>
<html>
<head><title>Goals - NutriVision</title><link rel="stylesheet" href="style.css">
<style>
.goal-option { display:flex; gap:15px; flex-wrap:wrap; margin:15px 0; }
.goal-card { flex:1; min-width:180px; padding:20px; border:2px solid #e0e0e0; border-radius:12px; text-align:center; cursor:pointer; transition:.3s; background:white; }
.goal-card:hover { border-color:var(--primary); transform:translateY(-3px); }
.goal-card.selected { border-color:var(--primary); background:#e8f5e9; }
.goal-card .icon { font-size:2.5rem; margin-bottom:10px; }
.goal-card h4 { margin-bottom:5px; }
.goal-card p { font-size:.85rem; color:var(--gray); }
.health-tip { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:20px; border-radius:12px; margin:20px 0; }
.health-tip h3 { color:white !important; }
body.dark-mode .goal-card { background:#1a1a3e; border-color:#2a2a4e; }
body.dark-mode .goal-card.selected { background:#1e3a1e; border-color:var(--primary); }
.suggestion-card { padding:20px; margin:15px 0; background:var(--light-gray); border-radius:12px; border-left:4px solid var(--primary); }
body.dark-mode .suggestion-card { background:#1a1a2e; }
.deficit-box { padding:15px; border-radius:10px; margin:10px 0; background:#fff8e1; border:1px solid #ffe082; }
.deficit-box h3 { color:#8d6e00 !important; }
.surplus-box { padding:15px; border-radius:10px; margin:10px 0; background:#e8f5e9; border:1px solid #a5d6a7; }
.surplus-box h3 { color:#2e7d32 !important; }
body.dark-mode .deficit-box { background:#3a2e1e; border-color:#8d6e00; }
body.dark-mode .surplus-box { background:#1e3a1e; border-color:#2e7d32; }
.calc-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #eee; }
body.dark-mode .calc-row { border-color:#2a2a4e; }
</style></head>
<body>
<div class="navbar">
    <h2>🎯 NutriVision</h2>
    <div class="nav-links">
        <span class="dark-toggle" onclick="toggleDarkMode()">🌙 Dark</span>
        <a href="dashboard.php" class="btn">📊 Dashboard</a>
    </div>
</div>

<div class="container">
    <h1>🎯 Set Your Goals</h1>
    
    <?php if($goal_saved): ?>
    <div class="alert alert-success">✅ Your goals have been saved! <a href="dashboard.php">View Dashboard</a></div>
    <?php endif; ?>

    <!-- Today's vs Target -->
    <div class="card">
        <h2>📊 Your Progress Today vs Target</h2>
        <div class="cards" style="grid-template-columns:repeat(4,1fr);">
            <div class="card" style="border-left:4px solid <?php echo $today_cal <= ($current_goal['target_calories'] ?? 2000) ? 'var(--primary)' : 'var(--danger)'; ?>;">
                <h3>🔥 Calories</h3>
                <h1><?php echo number_format($today_cal,0); ?></h1>
                <p>of <?php echo $current_goal['target_calories'] ?? 2000; ?> goal</p>
                <p style="font-weight:600;color:<?php echo ($today_cal <= ($current_goal['target_calories'] ?? 2000)) ? 'var(--primary)' : 'var(--danger)'; ?>;">
                    <?php $diff = ($current_goal['target_calories'] ?? 2000) - $today_cal; echo $diff >= 0 ? "Need $diff more" : "Exceeded by ".abs($diff); ?>
                </p>
            </div>
            <div class="card" style="border-left:4px solid var(--primary);">
                <h3>💪 Protein</h3>
                <h1><?php echo number_format($today_prot,1); ?>g</h1>
                <p>of <?php echo $current_goal['target_protein'] ?? 50; ?>g</p>
                <p style="font-weight:600;"><?php $pd = ($current_goal['target_protein']??50)-$today_prot; echo $pd>=0 ? "Need ".number_format($pd,1)."g more" : "✅ Great!"; ?></p>
            </div>
            <div class="card" style="border-left:4px solid var(--warning);">
                <h3>🌾 Carbs</h3>
                <h1><?php echo number_format($today_carbs,1); ?>g</h1>
                <p>of <?php echo $current_goal['target_carbs'] ?? 200; ?>g</p>
                <p style="font-weight:600;"><?php $cd = ($current_goal['target_carbs']??200)-$today_carbs; echo $cd>=0 ? "Need ".number_format($cd,1)."g more" : "✅ Done"; ?></p>
            </div>
            <div class="card" style="border-left:4px solid var(--secondary);">
                <h3>🥑 Fat</h3>
                <h1><?php echo number_format($today_fat,1); ?>g</h1>
                <p>of <?php echo $current_goal['target_fat'] ?? 50; ?>g</p>
                <p style="font-weight:600;"><?php $fd = ($current_goal['target_fat']??50)-$today_fat; echo $fd>=0 ? "Need ".number_format($fd,1)."g more" : "✅ Done"; ?></p>
            </div>
        </div>
    </div>

    <!-- BMR & Stats -->
    <div class="card" style="text-align:center;">
        <h2>Your Body Statistics</h2>
        <div class="cards" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
            <div class="card"><h3>⚖️ Weight</h3><h1><?php echo $weight; ?> kg</h1></div>
            <div class="card"><h3>📏 Height</h3><h1><?php echo $height; ?> cm</h1></div>
            <div class="card"><h3>🔥 BMR</h3><h1><?php echo round($bmr); ?></h1></div>
            <div class="card"><h3>⚡ Maintenance</h3><h1><?php echo $maintenance_calories; ?> kcal</h1></div>
        </div>
    </div>

    <!-- Goal Selection -->
    <form method="POST">
        <h2>What is your goal?</h2>
        <div class="goal-option">
            <div class="goal-card <?php echo ($user['goal']=='Weight Loss'||!$user['goal'])?'selected':''; ?>" onclick="selectGoal('Weight Loss',this)">
                <div class="icon">📉</div><h4>Weight Loss</h4><p>Reduce body fat</p>
            </div>
            <div class="goal-card <?php echo $user['goal']=='Muscle Gain'?'selected':''; ?>" onclick="selectGoal('Muscle Gain',this)">
                <div class="icon">💪</div><h4>Muscle Gain</h4><p>Build muscle</p>
            </div>
            <div class="goal-card <?php echo $user['goal']=='Maintain Weight'?'selected':''; ?>" onclick="selectGoal('Maintain Weight',this)">
                <div class="icon">⚖️</div><h4>Maintain</h4><p>Stay at current</p>
            </div>
            <div class="goal-card <?php echo $user['goal']=='Improve Health'?'selected':''; ?>" onclick="selectGoal('Improve Health',this)">
                <div class="icon">🌿</div><h4>Improve Health</h4><p>Better nutrition</p>
            </div>
        </div>
        
        <input type="hidden" name="goal_type" id="goalType" value="<?php echo $user['goal'] ?? 'Weight Loss'; ?>">

        <div class="card">
            <h2>📊 Target Nutrition Goals</h2>
            <div id="calcBox"></div>
            <div id="goalSuggestion" class="suggestion-card">🔄 Loading...</div>
            
            <label>🎯 Daily Calorie Goal (kcal)</label>
            <input type="number" name="daily_calorie_goal" id="calGoal" value="<?php echo $current_goal['target_calories'] ?? $maintenance_calories; ?>" required>
            
            <label>💪 Target Protein (g)</label>
            <input type="number" name="target_protein" id="protGoal" value="<?php echo $current_goal['target_protein'] ?? 50; ?>" step="5">
            
            <label>🌾 Target Carbs (g)</label>
            <input type="number" name="target_carbs" id="carbsGoal" value="<?php echo $current_goal['target_carbs'] ?? 200; ?>" step="10">
            
            <label>🥑 Target Fat (g)</label>
            <input type="number" name="target_fat" id="fatGoal" value="<?php echo $current_goal['target_fat'] ?? 50; ?>" step="5">
            
            <button type="submit" name="save_goal" class="btn btn-lg">💾 Save My Goals</button>
        </div>
    </form>

    <div class="health-tip" id="healthTip">
        <h3>📋 Official Health Recommendations</h3>
        <div id="tipContent"></div>
    </div>
</div>

<script>
function toggleDarkMode() { document.body.classList.toggle('dark-mode'); }

function selectGoal(goal, element) {
    document.querySelectorAll('.goal-card').forEach(c => c.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('goalType').value = goal;
    updateGoalValues(goal);
}

function updateGoalValues(goal) {
    const bmr = <?php echo $bmr; ?>;
    const maintenance = <?php echo $maintenance_calories; ?>;
    const weight = <?php echo $weight; ?>;
    const todayCal = <?php echo $today_cal; ?>;
    const todayProt = <?php echo $today_prot; ?>;
    const todayCarbs = <?php echo $today_carbs; ?>;
    const todayFat = <?php echo $today_fat; ?>;
    
    const suggestionDiv = document.getElementById('goalSuggestion');
    const tipDiv = document.getElementById('tipContent');
    const calcBox = document.getElementById('calcBox');
    
    let cal, protein, carbs, fat, suggestion, tip, calcHtml;
    
    if (goal === 'Weight Loss') {
        cal = Math.max(1200, maintenance - 500);
        protein = Math.round(weight * 1.6);
        carbs = Math.round(cal * 0.35 / 4);
        fat = Math.round(cal * 0.25 / 9);
        suggestion = '📉 <strong>Weight Loss Plan:</strong> Eat ' + cal + ' kcal/day (500 kcal deficit). High protein preserves muscle.';
        tip = '📖 <strong>UCSF Health & Max Healthcare:</strong> For weight loss, create 500 kcal daily deficit through diet + exercise. Aim for 0.5-1 kg/week. Focus on fiber-rich vegetables, whole grains, and plant proteins. Avoid crash diets!';
        calcHtml = `<div class="deficit-box">
            <h3>📉 Weight Loss - Calorie Deficit Calculator</h3>
            <div class="calc-row"><span>Maintenance Calories:</span><strong>${maintenance} kcal</strong></div>
            <div class="calc-row"><span>Target Deficit:</span><strong style="color:#c62828;">-500 kcal</strong></div>
            <div class="calc-row"><span style="font-weight:700;">Your Daily Goal:</span><strong style="color:#c62828;">${cal} kcal</strong></div>
            <div class="calc-row"><span>Eaten Today:</span><strong>${todayCal.toFixed(0)} kcal</strong></div>
            <div class="calc-row"><span>Remaining Today:</span><strong style="color:${cal - todayCal >= 0 ? 'green' : 'red'};">${(cal - todayCal).toFixed(0)} kcal ${cal - todayCal >= 0 ? 'left' : 'over'}</strong></div>
            <div class="calc-row"><span>Expected Weekly Loss:</span><strong style="color:green;">~0.5 kg</strong></div>
        </div>
        <div class="deficit-box">
            <h3>💪 Protein (Preserve Muscle)</h3>
            <div class="calc-row"><span>Need per day:</span><strong>${Math.round(weight * 1.6)}g</strong></div>
            <div class="calc-row"><span>Eaten Today:</span><strong>${todayProt.toFixed(1)}g</strong></div>
            <div class="calc-row"><span>Still Need:</span><strong style="color:${(Math.round(weight*1.6)-todayProt)>=0?'orange':'green'};">${Math.max(0,Math.round(weight*1.6)-todayProt).toFixed(1)}g more</strong></div>
            <div class="calc-row"><span>Good Sources:</span><strong>Paneer, Dal, Tofu, Sprouts, Curd</strong></div>
        </div>`;
    } else if (goal === 'Muscle Gain') {
        cal = maintenance + 400;
        protein = Math.round(weight * 1.8);
        carbs = Math.round(cal * 0.45 / 4);
        fat = Math.round(cal * 0.25 / 9);
        suggestion = '💪 <strong>Muscle Gain Plan:</strong> Eat ' + cal + ' kcal/day (400 kcal surplus). High protein for muscle synthesis.';
        tip = '📖 <strong>Max Healthcare:</strong> For muscle building, consume 1.6-2.2g protein/kg body weight. Eat within 2 hours post-workout. Strength train 3-4x/week. Include complex carbs for energy.';
        calcHtml = `<div class="surplus-box">
            <h3>📈 Muscle Gain - Calorie Surplus</h3>
            <div class="calc-row"><span>Maintenance:</span><strong>${maintenance} kcal</strong></div>
            <div class="calc-row"><span>Target Surplus:</span><strong style="color:#2e7d32;">+400 kcal</strong></div>
            <div class="calc-row"><span style="font-weight:700;">Your Goal:</span><strong style="color:#2e7d32;">${cal} kcal</strong></div>
            <div class="calc-row"><span>Eaten Today:</span><strong>${todayCal.toFixed(0)} kcal</strong></div>
            <div class="calc-row"><span>Need More:</span><strong style="color:${cal-todayCal>=0?'green':'orange'};">${Math.max(0,cal-todayCal).toFixed(0)} kcal</strong></div>
            <div class="calc-row"><span>Expected Weekly:</span><strong style="color:green;">~0.3-0.5 kg muscle</strong></div>
        </div>
        <div class="surplus-box">
            <h3>💪 High Protein for Growth</h3>
            <div class="calc-row"><span>Need per day:</span><strong>${Math.round(weight * 1.8)}g</strong></div>
            <div class="calc-row"><span>Eaten Today:</span><strong>${todayProt.toFixed(1)}g</strong></div>
            <div class="calc-row"><span>Still Need:</span><strong style="color:${(Math.round(weight*1.8)-todayProt)>=0?'orange':'green'};">${Math.max(0,Math.round(weight*1.8)-todayProt).toFixed(1)}g more</strong></div>
            <div class="calc-row"><span>Good Sources:</span><strong>Paneer, Soy, Dal, Greek Yogurt</strong></div>
        </div>`;
    } else if (goal === 'Maintain Weight') {
        cal = maintenance;
        protein = Math.round(weight * 1.2);
        carbs = Math.round(cal * 0.5 / 4);
        fat = Math.round(cal * 0.25 / 9);
        suggestion = '⚖️ <strong>Maintain Weight:</strong> Eat ' + cal + ' kcal/day. Balance all macros and stay active.';
        tip = '📖 <strong>WHO Guidelines:</strong> Maintain weight by balancing calories in = calories out. Aim for 150 min moderate activity/week. Focus on nutrient-dense whole foods.';
        calcHtml = `<div class="surplus-box">
            <h3>⚖️ Maintain Weight</h3>
            <div class="calc-row"><span>Maintenance Level:</span><strong>${maintenance} kcal</strong></div>
            <div class="calc-row"><span style="font-weight:700;">Your Goal:</span><strong>${cal} kcal</strong></div>
            <div class="calc-row"><span>Eaten Today:</span><strong>${todayCal.toFixed(0)} kcal</strong></div>
            <div class="calc-row"><span>Balance:</span><strong style="color:${Math.abs(cal-todayCal)<200?'green':'orange'};">${Math.abs(cal-todayCal).toFixed(0)} kcal ${cal-todayCal>=0?'under':'over'}</strong></div>
        </div>`;
    } else {
        cal = Math.max(1200, maintenance - 100);
        protein = Math.round(weight * 1.4);
        carbs = Math.round(cal * 0.45 / 4);
        fat = Math.round(cal * 0.3 / 9);
        suggestion = '🌿 <strong>Improve Health:</strong> Eat ' + cal + ' kcal/day. Focus on nutrient quality and whole foods.';
        tip = '📖 <strong>ICMR & UCSF Health:</strong> Reduce processed foods, added sugars, saturated fats. Increase fruits, vegetables, whole grains, and plant-based proteins. Stay hydrated with 2-3L water daily.';
        calcHtml = `<div class="surplus-box">
            <h3>🌿 Improve Health</h3>
            <div class="calc-row"><span>Maintenance:</span><strong>${maintenance} kcal</strong></div>
            <div class="calc-row"><span>Slight Deficit:</span><strong>-100 kcal</strong></div>
            <div class="calc-row"><span style="font-weight:700;">Your Goal:</span><strong>${cal} kcal</strong></div>
            <div class="calc-row"><span>Eaten Today:</span><strong>${todayCal.toFixed(0)} kcal</strong></div>
            <div class="calc-row"><span>Nutrient Focus:</span><strong>Vitamins, Minerals, Fiber</strong></div>
        </div>`;
    }
    
    document.getElementById('calGoal').value = cal;
    document.getElementById('protGoal').value = protein;
    document.getElementById('carbsGoal').value = carbs;
    document.getElementById('fatGoal').value = fat;
    suggestionDiv.innerHTML = suggestion;
    tipDiv.innerHTML = tip;
    calcBox.innerHTML = calcHtml;
}

// Initialize with current goal
updateGoalValues('<?php echo $user['goal'] ?? 'Weight Loss'; ?>');
</script>
</body>
</html>