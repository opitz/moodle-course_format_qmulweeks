<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Behat course-related steps definitions.
 *
 * @package    format_qmultopics
 * @category   test
 * @copyright  2020 Matthias Opitz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use Behat\Gherkin\Node\TableNode as TableNode,
    Behat\Mink\Exception\ExpectationException as ExpectationException,
    Behat\Mink\Exception\DriverException as DriverException,
    Behat\Mink\Exception\ElementNotFoundException as ElementNotFoundException;

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');
require_once(__DIR__ . '/../../../../../course/tests/behat/behat_course.php');

/**
 * Steps definitions related with putting sections under tabs.
 *
 * @copyright 2020 Matthias Opitz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_format_qmultopics extends behat_base {


    /**
     * Click in the given DOM element
     *
     * @Given /^I clicko on element "([^"]*)"$/
     * @param string $selector
     * @throws Exception
     */
    public function i_clicko_on_element($selector) {
        $page = $this->getSession()->getPage();
        $element = $page->find('css', $selector);

        if (empty($element)) {
            throw new Exception("No html element found for the selector ('$selector')");
        }

        $element->click();
    }

    /**
     * Checking if the Assessment Information area is visible.
     *
     * @Given /^the assignment information area should be visible$/
     * @throws ElementNotFoundException
     */
    public function the_assignment_information_area_should_be_visible() {
        $sectionxpath = "//div[@id='assessment_information_area']";
        $xpath = "//div[@id='assessment_information_area'][not(contains(@class, 'hidden'))]";

        // Area should be visible.
        $exception = new ExpectationException('The Assessment Information area is not visible', $this->getSession());
        $this->find('xpath', $xpath, $exception);
    }
}
