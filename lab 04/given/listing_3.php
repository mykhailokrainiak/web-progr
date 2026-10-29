<!DOCTYPE html>
<html>
<head> 
<title>Test</title>
<meta charset="utf-8">
</head>
<body>
    <h1>Тестова сторінка. ЛР4</h1>
    <h2>Сортування методом "бульбашки". Вкладені цикли</h2>
    <p>
    <?php 
    $arNumbers=array(); 
    for($i=0; $i<=100; $i++){
        $arNumbers[]=rand(0,100);
        echo $arNumbers[$i]." ";
        }
        ?>
        </p>
        <p>
        <?php

        for($i=0; $i<=100; $i++)
            for($j=0; $j<=99; $j++){
                if ($arNumbers[$j]>$arNumbers[$j+1]){ 
                    $tmp=$arNumbers[$j];$arNumbers[$j]=$arNumbers[$j+1]; 
                     $arNumbers[$j+1]=$tmp;
                    }
            } 
        foreach($arNumbers as $item){
            echo $item." ";
            } 
        ?> 
        </p>
        <time> 
            <?php
            echo date("Y-m-d H:i:s");
            ?> 
        </time>
</body> 
</html>
