<?php
session_start(); require_once "config.php"; $error="";
if($_SERVER['REQUEST_METHOD']==='POST'){
 $email=trim($_POST['email']);$password=$_POST['password'];$s=$conn->prepare("SELECT * FROM users WHERE email=?");$s->bind_param("s",$email);$s->execute();$u=$s->get_result()->fetch_assoc();
 if($u && password_verify($password,$u['password'])){$_SESSION['user_id']=$u['id'];header("Location:index.php");exit;} $error="Invalid email or password.";
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login</title><link rel="stylesheet" href="css/style.css"></head><body><header class="navbar"><a class="brand" href="index.php">📚 BookBridge</a><nav><a href="index.php">Home</a><a href="register.php">Register</a></nav></header><main class="auth"><div class="form-card"><span class="eyebrow">WELCOME BACK</span><h1>Login</h1><?php if($error):?><div class="alert"><?=htmlspecialchars($error)?></div><?php endif;?><form method="post" class="form"><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" required></label><button class="btn full">Login</button></form><p>New here? <a href="register.php">Create an account</a></p></div></main></body></html>
 if($u && password_verify($password,$u['password'])){$_SESSION['user_id']=$u['id'];$_SESSION['role']=$u['role'];header("Location:index.php");exit;} $error="Invalid email or password.";
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login</title><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/login.css"></head><body><header class="navbar"><a class="brand" href="index.php">📚 BookBridge</a><nav><a href="index.php">Home</a><a href="register.php">Register</a></nav></header><main class="auth"><div class="form-card"><span class="eyebrow">WELCOME BACK</span><h1>Login</h1><?php if($error):?><div class="alert"><?=htmlspecialchars($error)?></div><?php endif;?><form method="post" class="form"><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" required></label><button class="btn full">Login</button></form><p>New here? <a href="register.php">Create an account</a></p></div></main></body></html>
