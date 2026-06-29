<?php
header("HTTP/1.0 404 Not Found");
?>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 Not Found</title>
<style>
    body { font-family: sans-serif; text-align: center; padding: 150px; background: #fff; color: #333; }
    h1 { font-size: 50px; }
    p { font-size: 20px; }
</style>
</head><body>
<h1>404 Not Found</h1>
<p>The requested URL was not found on this server.</p>
<hr>
<address>Apache Server at <?php echo $_SERVER['HTTP_HOST']; ?> Port 80</address>
</body></html>
<?php
exit;
?>
