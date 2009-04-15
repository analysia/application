<?php
/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the North Rhine-Westphalian Library Service Center,
 * the Cooperative Library Network Berlin-Brandenburg, the Saarland University
 * and State Library, the Saxon State Library - Dresden State and University
 * Library, the Bielefeld University Library and the University Library of
 * Hamburg University of Technology with funding from the German Research
 * Foundation and the European Regional Development Fund.
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
 * @category   Application
 * @package    Tests_Modules_Webapi
 * @author     Henning Gerhardt (henning.gerhardt@slub-dresden.de)
 * @copyright  Copyright (c) 2009, OPUS 4 development team
 * @license    http://www.gnu.org/licenses/gpl.html General Public License
 * @version    $Id$
 */

require_once 'PHPUnit/Framework.php';

require_once 'modules/webapi/DocumentTests.php';
require_once 'modules/webapi/DoctypesTests.php';
require_once 'modules/webapi/LicenceTests.php';
require_once 'modules/webapi/PersonTests.php';
require_once 'modules/webapi/SearchTests.php';

/**
 * Collect all webapi tests.
 */
class Modules_Webapi_AllTests {

    /**
     * Set up a test suite with all webapi tests.
     *
     * @return mixed
     */
    public static function suite() {
        $suite = new PHPUnit_Framework_Testsuite('Opus Application Module: Webapi');
        $suite->addTestSuite('Modules_Webapi_DocumentTests');
        $suite->addTestSuite('Modules_Webapi_DoctypesTests');
        $suite->addTestSuite('Modules_Webapi_LicenceTests');
        $suite->addTestSuite('Modules_Webapi_PersonTests');
        $suite->addTestSuite('Modules_Webapi_SearchTests');
        return $suite;
    }

}