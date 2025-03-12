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
 * Block definition class for report_customcajasan
 *
 * @package    block_report_customcajasan
 * @copyright  2025 Cajasan
 * @author     Pedro Arias <soporte@ingeweb.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class block_report_customcajasan
 *
 * @package    block_report_customcajasan
 * @copyright  2025 Cajasan
 * @author     Pedro Arias <soporte@ingeweb.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_report_customcajasan extends block_base {

    /**
     * Initialize the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_report_customcajasan');
    }

    /**
     * Get the block content.
     *
     * @return stdClass The block content.
     */
    public function get_content() {
        global $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        $systemcontext = context_system::instance();
        // Check if user has permission to view the report.
        if (!has_capability('block/report_customcajasan:viewreport', $systemcontext)) {
            return $this->content;
        }

        // Add link to the report.
        $reporturl = new moodle_url('/blocks/report_customcajasan/report.php');
        
        $this->content->text = html_writer::tag('div', 
            html_writer::link($reporturl, 
                get_string('enrollment_report', 'block_report_customcajasan'), 
                ['class' => 'btn btn-primary btn-block mb-2']),
            ['class' => 'report-links']);

        return $this->content;
    }

    /**
     * Specify which page formats this block can be displayed in.
     *
     * @return array Array of page formats.
     */
    public function applicable_formats() {
        return [
            'admin' => true,
            'site-index' => true,
            'my' => true
        ];
    }

    /**
     * Can multiple instances of this block be used on a page?
     *
     * @return bool False means only one instance allowed.
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Does this block have global configuration?
     *
     * @return bool False as this block doesn't have config.
     */
    public function has_config() {
        return false;
    }

    /**
     * Does this block have instance-specific configuration?
     *
     * @return bool True if the block can be configured.
     */
    public function instance_allow_config() {
        return false;
    }
}