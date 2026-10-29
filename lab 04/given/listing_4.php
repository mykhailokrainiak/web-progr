<!DOCTYPE html>
<html>
<head>
<title>Test</title>
<meta charset="utf-8">
</head>
<body>
    <!-- Програма сортування масиву -->
    <h1>Тестова сторінка. ЛР4</h1>
    <h2>Баготовимірні масиви</h2>
    <p> 
    <?php
    $arPeople=array();
    $arPerson=array(
        "name"=>"Ivan",
        "surname"=>"Kostenko",
        "age"=>20,
        "student"=>true
        );
    $arPeople[]=$arPerson;
    $arPerson=array(
        "name"=>"Ruslan",
        "surname"=>"Kostenko",
        "age"=>12,
        "student"=>false
        );
    $arPeople[]=$arPerson;
    $arPerson=array(
        "name"=>"Petro",
        "surname"=>"Ilchenko",
        "age"=>18,
        "student"=>true
        );
    $arPeople[]=$arPerson;

    var_dump($arPerson);
    ?>
    </p>
    <time>
        <?php
        echo date("Y-m-d H:i:s"); 
        ?> 
    </time> 
</body>
</html>
