<?php
/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the Cooperative Library Network Berlin-Brandenburg,
 * the Saarland University and State Library, the Saxon State Library -
 * Dresden State and University Library, the Bielefeld University Library and
 * the University Library of Hamburg University of Technology with funding from
 * the German Research Foundation and the European Regional Development Fund.
 *
 * LICENCE
 * OPUS is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or any later version.
 * OPUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details. You should have received a copy of the GNU General Public License
 * along with OPUS; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @category    Application
 * @package     Import
 * @author      Sascha Szott
 * @copyright   Copyright (c) 2016
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id$
 */
class Application_Import_PackageReader {

    const METADATA_FILENAME = 'opus.xml';
    
    private $additionalEnrichments;
    
    function __construct($additionalEnrichments) {
        $this->additionalEnrichments = $additionalEnrichments;
    }
    
    public function readTarPackage($filename) {
        $logger = Zend_Registry::get('Zend_Log');
        if (!is_readable($filename)) {
            $errMsg = 'TAR archive ' . $filename . ' is not readable!';
            $logger->err($errMsg);
            throw new Exception($errMsg);
        }
        
        $xmlFile = 'phar://' . $filename . '/' . self::METADATA_FILENAME;
        if (!file_exists($xmlFile)) {
            return null;            
        }
        
        $content = file_get_contents($xmlFile);
        if (!$content || trim($content) == '') {
            return null;
        }
        
        $dirName = $this->createExtractionDir($filename, '.tar');
        $phar = new PharData($filename);
        $phar->extractTo($dirName);
        
        try {
            $statusDoc = $this->processOpusXML($content, $dirName);
        } finally {
            $this->removeExtractionDir($dirName);
        }
        return $statusDoc;
    }

    public function readZipPackage($filename) {
        $logger = Zend_Registry::get('Zend_Log');
        $logger->info('processing zip package ' . $filename);
        
        if (!is_readable($filename)) {
            $errMsg = 'ZIP archive ' . $filename . ' is not readable!';
            $logger->err($errMsg);
            throw new Exception($errMsg);
        }
                
        $zip = new ZipArchive();
        $zip->open($filename);
        $stat = $zip->statName(self::METADATA_FILENAME);
        $handle = $zip->getStream(self::METADATA_FILENAME);
        if ($handle == false || $stat['size'] == 0) {
            return null;
        }
        $content = fread($handle, $stat['size']);
        if (trim($content) == '') {
            return null;
        }
        
        $dirName = $this->createExtractionDir($filename, '.zip');
        $zip->extractTo($dirName);        
        $zip->close();
        
        try {
            $statusDoc = $this->processOpusXML($content, $dirName);
        }
        finally {
            $this->removeExtractionDir($dirName);
        }
        return $statusDoc;
    }
    
    private function removeExtractionDir($dirName) {
        $files = array_diff(scandir($dirName), array('.','..'));
        foreach ($files as $file) {
            if (is_dir($dirName . DIRECTORY_SEPARATOR . $file)) {
                $this->removeExtractionDir($dirName . DIRECTORY_SEPARATOR . $file);
            }
            else {
                unlink($dirName . DIRECTORY_SEPARATOR . $file);
            }
        }
        rmdir($dirName);
  } 
    
    private function createExtractionDir($filename, $packageType) {
        $dirName = dirname($filename) . DIRECTORY_SEPARATOR . basename($filename, $packageType);
        mkdir($dirName);
        return $dirName;
    }

    private function processOpusXML($xml, $dirName) {
        $logger = Zend_Registry::get('Zend_Log');        

        $importer = new Application_Import_Importer($xml, false, $logger);
        $importer->enableSwordContext();        
        $importer->setImportDir($dirName);
        
        $validMimeTypes = $this->getValidMimeTypes();
        $importer->setValidMimeTypes($validMimeTypes); 
        
        $importer->setAdditionalEnrichments($this->additionalEnrichments);
        $importCollection = new Sword_Model_ImportCollection();
        $importer->setImportCollection($importCollection->getCollection());
                
        $importer->run();
        return $importer->getStatusDoc();
    }
    
    private function getValidMimeTypes() {
        $config = Zend_Registry::get('Zend_Config');
        return $config->filetypes->mimetypes->toArray();
    }

}
