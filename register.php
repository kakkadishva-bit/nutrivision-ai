<?php
include 'db.php';

if(isset($_POST['register']))
{
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $weight = $_POST['weight'];
    $height = $_POST['height'];
    $goal = $_POST['goal'];
    $activity_level = $_POST['activity_level'];

    $query = "INSERT INTO users
    (full_name, email, password, age, gender, weight, height, goal, activity_level)

    VALUES

    ('$full_name','$email','$password','$age','$gender','$weight','$height','$goal','$activity_level')";

    $result = mysqli_query($conn, $query);

    if($result)
    {
        echo "Registration Successful";
    }
    else
    {
        echo "Registration Failed";
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <title>Register - NutriVision AI</title>

    <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="navbar">
    <h2>NutriVision AI</h2>
</div>

<div class="container">

<h1>Register</h1>

<form method="POST">

<input type="text"
       name="full_name"
       placeholder="Full Name"
       required>

<input type="email"
       name="email"
       placeholder="Email"
       required>

<input type="password"
       name="password"
       placeholder="Password"
       required>

<input type="number"
       name="age"
       placeholder="Age"
       required>

<select name="gender">

    <option>Male</option>
    <option>Female</option>

</select>

<input type="number"
       step="0.1"
       name="weight"
       placeholder="Weight (kg)"
       required>

<input type="number"
       step="0.1"
       name="height"
       placeholder="Height (cm)"
       required>

<select name="goal">

    <option>Weight Loss</option>
    <option>Muscle Gain</option>
    <option>Maintain Weight</option>

</select>

<select name="activity_level">

    <option>Beginner</option>
    <option>Intermediate</option>
    <option>Advanced</option>

</select>

<button type="submit"
        name="register"
        class="btn">

    Register

</button>

</form>

</div>

</body>
</html>