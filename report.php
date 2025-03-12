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
 * Report display page
 *
 * @package    block_report_customcajasan
 * @copyright  2025 Cajasan
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/blocks/report_customcajasan/lib.php');

// Verify login and capabilities
require_login();
$systemcontext = context_system::instance();
require_capability('block/report_customcajasan:viewreport', $systemcontext);

// Page setup
$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/blocks/report_customcajasan/report.php'));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('report_title', 'block_report_customcajasan'));
$PAGE->set_heading(get_string('report_title', 'block_report_customcajasan'));
$PAGE->requires->jquery();
$PAGE->requires->js_call_amd('block_report_customcajasan/report', 'init');

// Get filters
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$idnumber = optional_param('idnumber', '', PARAM_TEXT);
$startdate = optional_param('startdate', '', PARAM_TEXT);
$enddate = optional_param('enddate', '', PARAM_TEXT);
$download = optional_param('download', '', PARAM_TEXT);  // Cambiado a PARAM_TEXT para coincidir con el original
$format = optional_param('format', 'excel', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 100; // Number of records per page

// Prepare filter parameters
$filters = array();
if (!empty($categoryid)) {
    $filters['category'] = $categoryid;
}
if (!empty($courseid)) {
    $filters['course'] = $courseid;
}
if (!empty($idnumber)) {
    $filters['idnumber'] = $idnumber;
}
if (!empty($startdate)) {
    $filters['startdate'] = strtotime($startdate);
}
if (!empty($enddate)) {
    $filters['enddate'] = strtotime($enddate . ' 23:59:59');
}

// Check if any filter is selected
$filter_selected = !empty($categoryid) || !empty($courseid) || !empty($idnumber) || 
                  !empty($startdate) || !empty($enddate);

// Initialize variables
$enrollments = array();
$data = new stdClass();  // Cambiado para coincidir con la estructura original
$data->tabhead = array();
$data->table = array();
$totalcount = 0;

// Only get data if filters are applied
if ($filter_selected || $download) {  // Cambiado a coincidir con el original
    // Get all matching records for counting
    $enrollments = report_customcajasan_get_data($filters);
    $totalcount = count($enrollments);
    
    // Prepare data for display or export
    $data->tabhead = array(
        get_string('column_identificacion', 'block_report_customcajasan'),
        get_string('column_nombre', 'block_report_customcajasan'),
        get_string('column_correo', 'block_report_customcajasan'),
        get_string('column_unidad', 'block_report_customcajasan'),
        get_string('column_curso', 'block_report_customcajasan'),
        get_string('column_fecha_matricula', 'block_report_customcajasan'),
        get_string('column_fecha_finalizacion', 'block_report_customcajasan'),
        get_string('column_estado', 'block_report_customcajasan'),
        get_string('column_categoria', 'block_report_customcajasan')
    );
    
    // Apply pagination if not downloading
    if (!$download) {
        $enrollments = array_slice($enrollments, $page * $perpage, $perpage);
    }
    
    foreach ($enrollments as $enrollment) {
        $data->table[] = array(
            $enrollment->identificacion,
            $enrollment->nombre,
            $enrollment->correo,
            $enrollment->unidad,
            $enrollment->curso,
            $enrollment->fecha_matricula,
            $enrollment->fecha_finalizacion,
            $enrollment->estado,
            $enrollment->categoria
        );
    }
}

// Handle download requests - Cambiado para coincidir exactamente con el original
if ($download) {
    while (ob_get_level()) {
        ob_end_clean();
    }

    if ($format === 'csv') {
        report_customcajasan_export_csv($data->tabhead, $data->table, 'enrollment_report');
    } else {
        report_customcajasan_export_spreadsheet(
            $data->tabhead, 
            $data->table, 
            'enrollment_report', 
            $format, 
            get_string('report_title', 'block_report_customcajasan')
        );
    }
    exit;
}

// Display form and report
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report_title', 'block_report_customcajasan'));

// Help text explaining the date fields
echo html_writer::tag('div', 
    html_writer::tag('p', 
        html_writer::tag('strong', get_string('note_label', 'block_report_customcajasan') . ': ') . 
        get_string('certificate_date_note', 'block_report_customcajasan'),
        ['class' => 'small font-italic']
    ),
    ['class' => 'alert alert-info']
);

// Info text explaining that filters are required
echo html_writer::tag('div', 
    html_writer::tag('p', 
        html_writer::tag('strong', get_string('filters_required', 'block_report_customcajasan')),
        ['class' => 'small font-italic']
    ),
    ['class' => 'alert alert-warning']
);

// Filter form
echo html_writer::start_tag('form', array('id' => 'report-form', 'method' => 'get', 'class' => 'mb-4'));
echo html_writer::start_div('container-fluid');
echo html_writer::start_div('row');

// Category filter
echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::tag('label', get_string('option_category', 'block_report_customcajasan'), array('for' => 'categoryid'));
$categories = report_customcajasan_get_categories();
$categoryoptions = array('' => get_string('option_all', 'block_report_customcajasan'));
foreach ($categories as $category) {
    $indent = str_repeat('&nbsp;', $category->depth * 3);
    $categoryoptions[$category->id] = $indent . $category->name;
}
echo html_writer::select($categoryoptions, 'categoryid', $categoryid, false, array('class' => 'form-control', 'id' => 'categoryid'));
echo html_writer::end_div();

// Course filter
echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::tag('label', get_string('option_course', 'block_report_customcajasan'), array('for' => 'courseid'));
$courses = report_customcajasan_get_courses($categoryid);
$courseoptions = array('' => get_string('option_all', 'block_report_customcajasan'));
foreach ($courses as $course) {
    $courseoptions[$course->id] = $course->fullname;
}
echo html_writer::select($courseoptions, 'courseid', $courseid, false, array('class' => 'form-control', 'id' => 'courseid'));
echo html_writer::end_div();

// ID number filter
echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::tag('label', get_string('idnumber', 'block_report_customcajasan'), array('for' => 'idnumber'));
echo html_writer::empty_tag('input', array(
    'type' => 'text',
    'id' => 'idnumber',
    'name' => 'idnumber',
    'value' => $idnumber,
    'class' => 'form-control'
));
echo html_writer::end_div();

echo html_writer::end_div(); // End row
echo html_writer::start_div('row');

// Start date filter
echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::tag('label', get_string('start_date', 'block_report_customcajasan'), array('for' => 'startdate'));
echo html_writer::empty_tag('input', array(
    'type' => 'date',
    'id' => 'startdate',
    'name' => 'startdate',
    'value' => $startdate,
    'class' => 'form-control'
));
echo html_writer::end_div();

// End date filter
echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::tag('label', get_string('end_date', 'block_report_customcajasan'), array('for' => 'enddate'));
echo html_writer::empty_tag('input', array(
    'type' => 'date',
    'id' => 'enddate',
    'name' => 'enddate',
    'value' => $enddate,
    'class' => 'form-control'
));
echo html_writer::end_div();

// Submit button
echo html_writer::start_div('col-md-4 mb-3 d-flex align-items-end');
echo html_writer::empty_tag('input', array(
    'type' => 'submit',
    'value' => get_string('search'),
    'class' => 'btn btn-primary'
));
echo html_writer::end_div();

echo html_writer::end_div(); // End row
echo html_writer::end_div(); // End container-fluid
echo html_writer::end_tag('form');

// Results
echo html_writer::start_div('report-results mb-4');

if (!$filter_selected) {
    echo html_writer::tag('div', get_string('select_filter_first', 'block_report_customcajasan'), array('class' => 'alert alert-info'));
} else if (empty($data->table)) {
    echo html_writer::tag('div', get_string('no_data', 'block_report_customcajasan'), array('class' => 'alert alert-info'));
} else {
    echo html_writer::tag('div', get_string('total_records', 'block_report_customcajasan') . ': ' . $totalcount, array('class' => 'font-weight-bold mb-2'));
    
    // Table
    $table = new html_table();
    $table->head = $data->tabhead;
    $table->data = $data->table;
    $table->id = 'enrollment-report-table';
    $table->attributes['class'] = 'table table-striped table-bordered table-hover';
    
    echo html_writer::table($table);
    
    // Pagination
    $baseurl = new moodle_url('/blocks/report_customcajasan/report.php', [
        'categoryid' => $categoryid,
        'courseid' => $courseid,
        'idnumber' => $idnumber,
        'startdate' => $startdate,
        'enddate' => $enddate
    ]);
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $baseurl);
    
    // Download options - Replicado del código original
    echo html_writer::start_div('download-options mt-3');
    echo html_writer::start_tag('form', array('id' => 'downloadForm', 'method' => 'get'));
    
    // Hidden fields to preserve filters
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'categoryid', 'value' => $categoryid));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'courseid', 'value' => $courseid));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'idnumber', 'value' => $idnumber));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'startdate', 'value' => $startdate));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'enddate', 'value' => $enddate));
    
    // Format selection
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', get_string('option_download_format', 'block_report_customcajasan') . ':', array('for' => 'format', 'class' => 'mr-2'));
    $formatoptions = array(
        'excel' => get_string('option_download_excel', 'block_report_customcajasan'),
        'ods' => get_string('option_download_ods', 'block_report_customcajasan'),
        'csv' => get_string('option_download_csv', 'block_report_customcajasan')
    );
    echo html_writer::select($formatoptions, 'format', $format, false, array('class' => 'form-control d-inline w-auto'));
    
    // Download button - Modificado para coincidir exactamente con el original
    echo html_writer::empty_tag('button', array(
        'type' => 'submit',
        'name' => 'download',
        'value' => '1',
        'class' => 'btn btn-primary ml-2'
    ));
    echo get_string('btn_download', 'block_report_customcajasan');
    echo html_writer::end_tag('button');
    
    echo html_writer::end_div(); // End form-group
    echo html_writer::end_tag('form');
    echo html_writer::end_div(); // End download-options
}

echo html_writer::end_div(); // End report-results

echo $OUTPUT->footer();