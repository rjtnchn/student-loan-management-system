<?php
    $name = $_POST['name'];
    $email = $_POST['email'];
    $address = $_POST['address'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=div, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <div><?php echo $name ?></div>
    <div><?php echo $email ?></div>
    <div><?php echo $address? "": "Nothing to display" ?></div>
</body>
</html>