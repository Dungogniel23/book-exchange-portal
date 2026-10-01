<?php
session_start(); require_once "config.php"; $error="";
if($_SERVER['REQUEST_METHOD']==='POST'){
 $name=trim($_POST['name']);$email=trim($_POST['email']);$password=$_POST['password'];
 if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<6)$error="Please enter valid details. Password must be at least 6 characters.";
 else{$s=$conn->prepare("SELECT id FROM users WHERE email=?");$s->bind_param("s",$email);$s->execute();if($s->get_result()->num_rows)$error="Email is already registered.";else{$hash=password_hash($password,PASSWORD_DEFAULT);$s=$conn->prepare("INSERT INTO users(name,email,password) VALUES(?,?,?)");$s->bind_param("sss",$name,$email,$hash);$s->execute();$_SESSION['user_id']=$conn->insert_id;header("Location:index.php");exit;}}
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Register</title><link rel="stylesheet" href="css/style.css"></head><body><header class="navbar"><a class="brand" href="index.php">📚 BookBridge</a><nav><a href="index.php">Home</a><a href="login.php">Login</a></nav></header><main class="auth"><div class="form-card"><span class="eyebrow">JOIN THE COMMUNITY</span><h1>Create Account</h1><?php if($error):?><div class="alert"><?=htmlspecialchars($error)?></div><?php endif;?><form method="post" class="form"><label>Full Name<input name="name" required></label><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" minlength="6" required></label><button class="btn full">Register</button></form><p>Already have an account? <a href="login.php">Login</a></p></div></main></body></html>
