<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Paradox\Paradox;

// $filepath = 'D:\MirServer\Mud2\DB\Magic.DB';
// $filepath = 'D:\MirServer\Mud2\DB\Monster.DB';
$filepath = 'D:\MirServer\Mud2\DB\StdItems.DB';

$paradox = new Paradox($filepath);
// var_dump($paradox->header()->all());
// var_dump($paradox->types());
// var_dump($paradox->tableName());
// var_dump($paradox->names());
var_dump(array_column($paradox->getData(), 'Name'));