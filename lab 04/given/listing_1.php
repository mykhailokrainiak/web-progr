<!DOCTYPE html>
<html>
<head> 
<title>Test</title>
<meta charset="utf-8">
</head>
<body>
    <h1>Тестова сторінка. ЛР4</h1>
    <h2>Виведення парних чисел for</h2>
    <?php
    for($i=0; $i<=100; $i++){
            if ($i%2==0) echo $i."<br>\n";
            }
    ?>
    <h2>Виведення парних чисел while</h2>
    <?php
    $i=0;
    while($i<=100{ 
        if ($i%2==0) echo $i."<br>\n";
        $i++;
        } 
        ?>
    <time>
        <?php
        echo date("Y-m-d H:i:s");
        ?> 
    </time>
</body>
</html>
