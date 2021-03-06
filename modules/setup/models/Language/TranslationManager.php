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
 * @package     Module_Setup
 * @author      Edouard Simon (edouard.simon@zib.de)
 * @copyright   Copyright (c) 2008-2013, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id$
 */

/**
 *
 */
class Setup_Model_Language_TranslationManager {
    /**
     * sort by translation unit
     */

    const SORT_UNIT = 'unit';

    /**
     * sort by application module
     */
    const SORT_MODULE = 'module';

    /**
     * sort by application module directory
     */
    const SORT_DIRECTORY = 'directory';

    /**
     * sort by filename
     */
    const SORT_FILENAME = 'filename';

    /**
     * sort by language
     */
    const SORT_LANGUAGE = 'language';

    /**
     * sort by translation unit variant
     */
    const SORT_VARIANT = 'variant';

    /**
     * array holding modules to include
     */
    protected $_modules = array();

    /**
     * string used to filter translation units
     */
    protected $_filter;

    /**
     * Set Modules to include
     *
     * @param array $modules Modules to include
     *
     */
    public function setModules($array) {
        $this->_modules = $array;
    }

    /**
     * Set filter used to filter translation units.
     * Only units which contain filter are returned.
     *
     *
     */
    public function setFilter($string) {
        $this->_filter = $string;
    }

    /**
     * Returns translations in modules set via @see setModules()
     * and (optionally) matching string set via @see setFilter()
     *
     * @param string $sortKey   Key used to sort result array.
     *                          Valid keys are defined as class constants
     * @param int $sortOrder    Sort order as expected by @see array_multisort()
     * @throw Setup_Model_FileNotReadableException Thrown if loading tmx file(s) fails.
     */
    public function getTranslations($sortKey = self::SORT_UNIT, $sortOrder = SORT_ASC) {
        $fileData = $this->getFiles();
        $translations = array();
        $sortArray = array();
        foreach ($fileData as $module => $files) {
            foreach ($files as $dir => $filenames) {
                foreach ($filenames as $fileName) {
                    $relativeFilePath = "$module/$dir/$fileName";
                    $filePath = APPLICATION_PATH . "/modules/$relativeFilePath";
                    $tmxFile = new Application_Util_TmxFile();
                    if ($tmxFile->load($filePath)) {
                        $translationUnits = $tmxFile->toArray();
                        foreach ($translationUnits as $key => $values) {
                            if (empty($this->_filter) || strpos($key, $this->_filter) !== false) {
                                foreach ($values as $lang => $value) {
                                    $row = array(
                                        'unit' => $key,
                                        'module' => $module,
                                        'directory' => $dir,
                                        'filename' => $fileName,
                                        'language' => $lang,
                                        'variant' => $value);
                                    $translations[] = $row;
                                    $sortArray[] = $row[$sortKey];
                                }
                            }
                        }
                    }
                    else {
                        throw new Setup_Model_FileNotReadableException($filePath);
                    }
                }
            }
        }
        array_multisort($sortArray, $sortOrder, SORT_STRING, $translations);
        return $translations;
    }

    /**
     * returns an array containing all translation files found for all modules
     * set via @see setModules()
     */
    public function getFiles() {
        $modules = array();
        $languageDirs = array('language', 'language_custom');
        foreach ($this->_modules as $moduleName) {
            $moduleFiles = array();
            $moduleSubDirs = new RecursiveDirectoryIterator(
                realpath(APPLICATION_PATH . "/modules/$moduleName"), FilesystemIterator::CURRENT_AS_SELF
            );
            foreach ($moduleSubDirs as $moduleSubDir) {
                if ($moduleSubDir->isDir()) {
                    $dirName = $moduleSubDir->getFilename();
                    if (in_array($dirName, $languageDirs)) {
                        $tmxFiles = $moduleSubDir->getChildren();
                        foreach ($tmxFiles as $tmxFile) {
                            $tmxFileName = $tmxFile->getFilename();
                            if ($tmxFile->isFile() && substr($tmxFileName, -4) == '.tmx') {
                                $moduleFiles[$dirName][] = $tmxFile->getFilename();
                            }
                        }
                    }
                }
            }
            if (!empty($moduleFiles)) {
                $modules[$moduleName] = $moduleFiles;
            }
        }
        return $modules;
    }

}
