<?php

namespace Ryumin\BitrixDiskEx;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class BitrixDisk
{
    private $userId;

    /**
     * @param $userId
     */
    public function __construct($userId=null)
    {
        $this->userId = $userId;
    }


    /**
     * @return mixed
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param mixed $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    //получаем список всех пользователей
    public function getAllUsersIds()
    {
        $filter = array(
            'ACTIVE' => 'Y'
        );

        if(\CModule::IncludeModule("main"))
        {
            $rsUsers = \CUser::GetList(($by = "id"), ($order = "desc"), $filter);
            while ($arItem = $rsUsers->GetNext()) {
                $usersArray[] = $arItem['ID'];
            }
        }
        return $usersArray;
    }

    //зипуем источник в указанное место
    public function zip($source, $destination)
    {
        if (!extension_loaded('zip') || !file_exists($source)) {
            return false;
        }

        $zip = new ZipArchive();
        if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
            return false;
        }

        $source = str_replace('\\', DIRECTORY_SEPARATOR, realpath($source));
        $source = str_replace('/', DIRECTORY_SEPARATOR, $source);

        if (is_dir($source) === true) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source),
                RecursiveIteratorIterator::SELF_FIRST);

            foreach ($files as $file) {
                $file = str_replace('\\', DIRECTORY_SEPARATOR, $file);
                $file = str_replace('/', DIRECTORY_SEPARATOR, $file);

                if ($file == '.' || $file == '..' || empty($file) || $file == DIRECTORY_SEPARATOR) {
                    continue;
                }
                // Ignore "." and ".." folders
                if (in_array(substr($file, strrpos($file, DIRECTORY_SEPARATOR) + 1), array('.', '..'))) {
                    continue;
                }

                $file = realpath($file);
                $file = str_replace('\\', DIRECTORY_SEPARATOR, $file);
                $file = str_replace('/', DIRECTORY_SEPARATOR, $file);

                if (is_dir($file) === true) {
                    $d = str_replace($source . DIRECTORY_SEPARATOR, '', $file);
                    if (empty($d)) {
                        continue;
                    }
                    $zip->addEmptyDir($d);
                } elseif (is_file($file) === true) {
                    $zip->addFromString(str_replace($source . DIRECTORY_SEPARATOR, '', $file),
                        file_get_contents($file));
                } else {
                    // do nothing
                }
            }
        } elseif (is_file($source) === true) {
            $zip->addFromString(basename($source), file_get_contents($source));
        }

        return $zip->close();
    }

    //получение пути файла диска по id файла
    public function getRealFileFromDiskById($FileId)
    {
        \Bitrix\Main\Loader::includeModule('disk');
        $resObjects = \Bitrix\Disk\Internals\ObjectTable::getList([
            'select' => [
                'NAME', 'FILE_ID'
            ],
            'filter' => [
                '=ID' => $FileId,
            ]
        ]);

        if ($arObject = $resObjects->fetch()) {


            $arObject['PATH'] = \CFile::GetPath($arObject['FILE_ID']);
            // echo "<br>";
            $arObject['FULL_PATH'] = $_SERVER['DOCUMENT_ROOT'] . $arObject['PATH'];
            return $arObject['PATH'];
        }

        return false;
    }

    //получаем все из хранилища пользователя
    public function getAllfromUserStorage($userId)
    {
        //получаем хранилища
        if (\Bitrix\Main\Loader::includeModule('disk')) {
            $driver = \Bitrix\Disk\Driver::getInstance();

            //получаем хранилище по id пользователя
            $storage = $driver->getStorageByUserId($userId); //по пользователю
            //получаем папки пользователя
            $userFolders = $storage->getRootObject()->getChildren($storage->getSecurityContext($userId), array());


            foreach ($userFolders as $key => $folder) {

                //формируем массив из id папок пользователя
                $userFolderIds[] = $userFolders[$key]->getId();

            }

        }
        return $userFolderIds;
    }

    //выполняем создание папок и копирование в них инфы из битрикс диска
    public function getRecurciveFolder($diskObject, $securitycontext, &$arFolderList)
    {

        if ($diskObject instanceof \Bitrix\Disk\Folder) {

            $arFolder = [
                'NAME' => $diskObject->getName(),
                'IS_FOLDER' => true,
                'CHILDRENS' => [],
            ];


            $home = $_SERVER['DOCUMENT_ROOT'] . "/";
            $dir = $home . "files-from-my-disc/" . $diskObject->getStorage()->getRootObject()->getOriginalName() . "/" . $diskObject->getName(); //путь до папки
            mkdir($dir, 0777, true);


            $arChildrens = $diskObject->getChildren($securitycontext);

            foreach ($arChildrens as $childObject) {
                $this->getRecurciveFolder($childObject, $securitycontext, $arFolder['CHILDRENS']);
            }

            $arFolderList[] = $arFolder;

        } else {

            if ($diskObject instanceof \Bitrix\Disk\File) {
                $urlManager = \Bitrix\Disk\Driver::getInstance()->getUrlManager();
                $downloadUrl = $urlManager->getUrlForDownloadFile($diskObject);
            }

            $arFolderList[] = [
                'NAME' => $diskObject->getName(),
                'IS_FOLDER' => false,
                'FILE_PATH' => $downloadUrl
            ];


            $file = $_SERVER["REQUEST_SCHEME"] . '://' . $_SERVER['HTTP_HOST'] . $this->getRealFileFromDiskById($diskObject->getId());
            $file_name = $diskObject->getName();
            $path = $_SERVER['DOCUMENT_ROOT'] . "/files-from-my-disc/" . $diskObject->getStorage()->getRootObject()->getOriginalName() . "/" . $diskObject->getParent()->getName() . '/' . $file_name;
            $Headers = @get_headers($file);
            $context = stream_context_create(array('http' => array('user_agent' => 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322')));


            if (preg_match("|200|", $Headers[0])) {

                file_put_contents($path, file_get_contents($file, false, $context));

            } else {
                echo "Not Found";
            }
        }
    }

    //Переносим файлы из корня папки битрикс диска в специальную папку "файлы из корня диска"
    public function putRootContentFromBitrixDiskToSpecialFolder(){

        if (\Bitrix\Main\Loader::includeModule('disk')) {

            $storage = \Bitrix\Disk\Driver::getInstance()->getStorageByUserId($this->userId);
            if ($storage) {

                $folder = $storage->getRootObject();
                $rootFiles = $folder->getChildren(\Bitrix\Disk\Driver::getInstance()->getFakeSecurityContext());

                //создаем папку для корневых файлов
                $folder = $storage->addFolder(
                    array(
                        'NAME' => 'файлы из корня диска',
                        'CREATED_BY' => $this->userId
                    )
                );

                foreach ($rootFiles as $rootFile) {
                    if ($rootFile instanceof \Bitrix\Disk\File) {
                        $rootFileMass[] = $rootFile->getId();

                        //переносим файлы из корня в нашу папку
                        if ($rootFile) {
                            $rootFile->moveTo($folder, $this->userId); //объект папки, пользователь
                        }
                    }
                }
            }
        }
    }

    public function copyBitrixDiscContentToLocalFolder($id){

        $this->setUserId($id);

        //помещаем файлы из корня диска в специальную папку
        $this->putRootContentFromBitrixDiskToSpecialFolder();

        $storage = \Bitrix\Disk\Driver::getInstance()->getStorageByUserId($this->userId);
        $storageOwnerName = $storage->getRootObject()->getOriginalName();

        //создаем папку в корне сайта
        if (!file_exists($_SERVER['DOCUMENT_ROOT']."/files-from-my-disc/".$storageOwnerName."/")) {
            mkdir($_SERVER['DOCUMENT_ROOT']."/files-from-my-disc/".$storageOwnerName."/", 0777, true);
        }


        try
        {
            if ( !\Bitrix\Main\Loader::includeModule('disk') )
            {
                throw new \Exception("Не подклчюен модуль диска");
            }

            /* @var Disk\Driver Объект 'управленца' хранилищами */
            $driver = \Bitrix\Disk\Driver::getInstance();

            /* @var \Bitrix\Disk\FakeSecurityContext Объект прав - читаем все, а не то что доступно пользователю */
            $securityContext = $driver->getFakeSecurityContext();

            /* @var array Массив куда будем складывать нашу структуру*/
            $arFolderList = [];


            //получаем массив id папок пользователя
            foreach ($this->getAllfromUserStorage($this->userId) as $userFolderId)
            {
                $this->getRecurciveFolder( \Bitrix\Disk\Folder::loadById($userFolderId), $securityContext, $arFolderList );
            }

        }
        catch( Exception $e )
        {
            echo "Произошла ошибка: \r\n";
            var_dump($e->getMessage());
        }

    }

}
