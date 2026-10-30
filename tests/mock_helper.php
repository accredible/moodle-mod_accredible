<?php
// This file is part of the Accredible Certificate module for Moodle - http://moodle.org/
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

namespace mod_accredible;

/**
 * Builds partial mocks on every PHPUnit version Moodle ships.
 *
 * MockBuilder::onlyMethods() arrived in PHPUnit 8.3, and setMethods() was
 * removed in PHPUnit 10. Moodle 3.9 ships PHPUnit 7.5, so it needs the latter.
 *
 * @package    mod_accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait mock_helper {
    /**
     * Get a mock builder that replaces only the given methods.
     *
     * @param string $classname The class to mock.
     * @param string[] $methods The methods to replace; all others keep their real code.
     * @return \PHPUnit\Framework\MockObject\MockBuilder
     */
    protected function get_partial_mock_builder(string $classname, array $methods) {
        $builder = $this->getMockBuilder($classname);
        if (method_exists($builder, 'onlyMethods')) {
            return $builder->onlyMethods($methods);
        }
        return $builder->setMethods($methods);
    }
}
