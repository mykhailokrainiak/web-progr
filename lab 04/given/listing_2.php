<!DOCTYPE html>
<html>
<head>
<title>Test</title> 
<meta charset="utf-8"> 
</head>
<body>
    <h1>Тестова сторінка. ЛР4</h1>
    <h2>Створення асоціативного масиву</h2>
    <?php
    $arNumbers=array();
    for($i=0; $i<=100; $i++){
        $key='k'.$i;
        $arNumbers[$key]=$i;
        }
        var_dump($arNumbers);
        ?>
    <time>
        <?php 
        echo date("Y-m-d H:i:s");
        ?>
    </time> 
</body>
</html>
