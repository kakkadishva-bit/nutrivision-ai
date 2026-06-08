<?php
session_start();

include 'db.php';

if(isset($_POST['login']))
{
    $email = $_POST['email'];
    $password = $_POST['password'];

    $query = "SELECT * FROM users
              WHERE email='$email'
              AND password='$password'";

    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0)
    {
        $user = mysqli_fetch_assoc($result);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];

        header("Location: dashboard.php");
    }
    else
    {
        echo "Invalid Email or Password";
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <title>Login - NutriVision AI</title>

    <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="navbar">
    <h2>NutriVision AI</h2>
</div>

<div class="container">

<h1>Login</h1>

<form method="POST">

<input type="email"
       name="email"
       placeholder="Enter Email"
       required>

<input type="password"
       name="password"
       placeholder="Enter Password"
       required>

<button type="submit"
        name="login"
        class="btn">

    Login

</button>

</form>

</div>

</body>
</html>