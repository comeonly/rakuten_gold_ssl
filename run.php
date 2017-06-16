#!/usr/local/bin/php

<?php
require __DIR__ . '/vendor/autoload.php';

use Touki\FTP\FTP;
use Touki\FTP\Model\Directory;
use Touki\FTP\Model\File;
use Touki\FTP\Connection\Connection;
use Touki\FTP\FTPWrapper;
use Touki\FTP\PermissionsFactory;
use Touki\FTP\FilesystemFactory;
use Touki\FTP\WindowsFilesystemFactory;
use Touki\FTP\DownloaderVoter;
use Touki\FTP\UploaderVoter;
use Touki\FTP\CreatorVoter;
use Touki\FTP\DeleterVoter;
use Touki\FTP\Manager\FTPFilesystemManager;

if ($argc < 3) {
    exit("usage: php run.php <username> <password>\n");
}

$username = $argv[1];
$password = $argv[2];
$dry = (!empty($argv[3]) && $argv[3] === 'dry') ? true : false;

$connection = new Connection('ftp.rakuten.ne.jp', $username, $password, $port = 16910, $timeout = 90, $passive = true);

$connection->open();

$ftp = initFtp($connection);

if (!$ftp) {
    exit("initialized false something wrong.\n");
}

echo "start convert.\n";

getFiles($ftp, '/', $dry);

exit("done.\n");

function getFiles($ftp, $path, $dry = false) {
    $list = $ftp->findFiles(new Directory($path));
    foreach ($list as $file) {
        $filePath = $file->getRealpath();
        if (preg_match('/\.html?$/', $filePath)) {
            $ftp->download('/tmp/tmp.html', $file);
            $target = file_get_contents('/tmp/tmp.html');

            if ($dry) {
                echo '##target file : ' . $filePath . "\n";
                if (preg_match_all('/(?:src|href)=["\']?http:\/\/[^"\' >]+/', $target, $matches)) {
                    echo implode("\n", $matches[0]) . "\n";
                } else {
                    echo "couldn't find any http links\n";
                }
            } else {
                $target = str_replace('http://', 'https://', $target);
                file_put_contents('/tmp/tmp.html', $target);
                $ftp->upload(new File($filePath), '/tmp/tmp.html');
            }

        }
    }
    $list = $ftp->findDirectories(new Directory($path));
    foreach ($list as $directory) {
        getFiles($ftp, $directory->getRealpath());
    }
}

function initFtp($connection) {
    /**
     * The wrapper is a simple class which wraps the base PHP ftp_* functions
     * It needs a Connection instance to get the related stream
     */
    $wrapper = new FTPWrapper($connection);

    /**
     * This factory creates Permissions models from a given permission string (rw-)
     */
    $permFactory = new PermissionsFactory;

    /**
     * This factory creates Filesystem models from a given string, ex:
     *     drwxr-x---   3 vincent  vincent      4096 Jul 12 12:16 public_ftp
     *
     * It needs the PermissionsFactory so as to instanciate the given permissions in
     * its model
     */
    $fsFactory = new FilesystemFactory($permFactory);

    /**
     * If your server runs on WINDOWS, you can use a Windows filesystem factory instead
     */
    // $fsFactory = new WindowsFilesystemFactory;

    /**
     * This manager focuses on operations on remote files and directories
     * It needs the FTPWrapper so as to do operations on the serveri
     * It needs the FilesystemFfactory so as to create models
     */
    $manager = new FTPFilesystemManager($wrapper, $fsFactory);


    /**
     * This is the downloader voter. It loads multiple DownloaderVotable class and
     * checks which one is needed on given options
     */
    $dlVoter = new DownloaderVoter;

    /**
     * Loads up default FTP Downloaders
     * It needs the FTPWrapper to be able to share them with the downloaders
     */
    $dlVoter->addDefaultFTPDownloaders($wrapper);

    /**
     * This is the uploader voter. It loads multiple UploaderVotable class and
     * checks which one is needed on given options
     */
    $ulVoter = new UploaderVoter;

    /**
     * Loads up default FTP Uploaders
     * It needs the FTPWrapper to be able to share them with the uploaders
     */
    $ulVoter->addDefaultFTPUploaders($wrapper);

    /**
     * This is the creator voter. It loads multiple CreatorVotable class and
     * checks which one is needed on the given options
     */
    $crVoter = new CreatorVoter;

    /**
     * Loads up the default FTP creators.
     * It needs the FTPWrapper and the FTPFilesystemManager to be able to share
     * them whith the creators
     */
    $crVoter->addDefaultFTPCreators($wrapper, $manager);

    /**
     * This is the deleter voter. It loads multiple DeleterVotable classes and
     * checks which one is needed on the given options
     */
    $deVoter = new DeleterVoter;

    /**
     * Loads up the default FTP deleters.
     * It needs the FTPWrapper and the FTPFilesystemManager to be able to share
     * them with the deleters
     */
    $deVoter->addDefaultFTPDeleters($wrapper, $manager);

    /**
     * Finally creates the main FTP
     * It needs the manager to do operations on files
     * It needs the download voter to pick-up the right downloader on ->download
     * It needs the upload voter to pick-up the right uploader on ->upload
     * It needs the creator voter to pick-up the right creator on ->create
     * It needs the deleter voter to pick-up the right deleter on ->delete
     */
    return new FTP($manager, $dlVoter, $ulVoter, $crVoter, $deVoter);

}
?>
