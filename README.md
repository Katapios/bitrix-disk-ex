# bitrix-disk-ex

___Данный класс содержит расширенные методы по работе с диском битрикс24___

_:warning: +метод, который переносит все файлы и папки из битрикс24 диска и зипует их_

## Для установки используйте команду:
```
composer require ryumin/bitrix-disk-ex:@dev
```

## Описание методов, входящих в данный класс:

| Название метода | Описание метода |
|----------------|----------------|
| **getAllUsersIds** | Получаем список всех пользователей |
| **zip** | Зипуем источник в указанное место |
| **getRealFileFromDiskById** | Получение пути файла диска по id файла |
| **getAllfromUserStorage** | Получаем все из хранилища пользователя |
| **putRootContentFromBitrixDiskToSpecialFolder** | Переносим файлы из корня папки битрикс диска в специальную папку |
| :white_check_mark:  **copyBitrixDiscContentToLocalFolder** | Основной метод, который переносит все файлы и папки из битрикс24 диска и зипует их |


### Пример подключения в любом файле:

``` php
//подключаем пространство имен со скаченной библиотекой
use Ryumin\BitrixDiskEx\BitrixDisk;


//создаем экземпляр класса и пользуемся методами
$disk = new BitrixDisk();

$disk-> ...

```

### Пример подключения в любом файле как php скрипт:

``` php

$_SERVER["DOCUMENT_ROOT"] = "/var/www/public_html"; // Ваш путь до корневой директории;
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

-------------------------------------------------------------------------------------------------------------------------------------------------

//подключаем пространство имен со скаченной библиотекой
use Ryumin\BitrixDiskEx\BitrixDisk;

//создаем экземпляр класса и пользуемся методами
$disk = new BitrixDisk();

//получаем пользователей в массив
$UserIds = $disk->getAllUsersIds();

//пройдясь по пользователям в массиве, формируем папки
foreach ($UserIds as $userId) {
    $disk->copyBitrixDiscContentToLocalFolder($userId);
}

//зипуем полученные папки
$disk->zip($_SERVER['DOCUMENT_ROOT'] . "/files-from-my-disc/", $_SERVER['DOCUMENT_ROOT'] . '/compressed.zip');

-------------------------------------------------------------------------------------------------------------------------------------------------

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php"); //если ругается на него php, то комментим и дальше пользуемся
```

### Вызываем скрипт из консоли:

```
php путь до файла со скриптом.php
```
