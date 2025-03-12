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
 * English language strings for report_customcajasan
 *
 * @package    block_report_customcajasan
 * @copyright  2025 Cajasan
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Plugin name
$string['pluginname'] = 'Cajasan Custom Report';
$string['report_customcajasan'] = 'Cajasan Enrollment Report';
$string['enrollment_report'] = 'Enrollment Report';

// Capabilities
$string['report_customcajasan:addinstance'] = 'Add a new Cajasan Report block';
$string['report_customcajasan:myaddinstance'] = 'Add a new Cajasan Report block to the My Moodle page';
$string['report_customcajasan:viewreport'] = 'View Cajasan Enrollment Report';

// Table Headers
$string['column_identificacion'] = 'IDENTIFICACIÓN';
$string['column_nombres'] = 'NOMBRES';
$string['column_apellidos'] = 'APELLIDOS';
$string['column_correo'] = 'CORREO';
$string['column_unidad'] = 'UNIDAD';
$string['column_curso'] = 'CURSO';
$string['column_fecha_matricula'] = 'FECHA DE MATRICULA';
$string['column_fecha_certificado'] = 'FECHA DE EMISIÓN CERTIFICADO';
$string['column_fecha_finalizacion'] = 'FECHA DE FINALIZACIÓN CURSO';
$string['column_estado'] = 'ESTADO';
$string['column_categoria'] = 'CATEGORÍA';

// Status values
$string['state_completo'] = 'COMPLETO';
$string['state_enprogreso'] = 'EN PROGRESO';
$string['state_finalizado'] = 'FINALIZADO';
$string['state_consulta'] = 'CONSULTA';
$string['status_explanation'] = 'States';

// Form Options
$string['option_all'] = 'All';
$string['option_category'] = 'Category';
$string['option_course'] = 'Course';
$string['option_firstname'] = 'First Name';
$string['option_lastname'] = 'Last Name';
$string['option_estado'] = 'Status';
$string['option_filter_by_letter'] = 'Filter by letter';
$string['option_download_format'] = 'Download Format';
$string['option_download_excel'] = 'Excel';
$string['option_download_ods'] = 'ODS';
$string['option_download_csv'] = 'CSV';

// Other
$string['report_title'] = 'Enrollment Report';
$string['btn_download'] = 'Download';
$string['total_records'] = 'Total Records';
$string['idnumber'] = 'ID Number';
$string['start_date'] = 'Start Date';
$string['end_date'] = 'End Date';
$string['no_data'] = 'No enrollment data found';
$string['access_denied'] = 'You do not have permission to view this report';
$string['filters_required'] = 'You must select at least one filter to view data';
$string['select_filter_first'] = 'Please select at least one filter to view the report data';
$string['note_label'] = 'Note';
$string['status_note'] = 'Courses that do not track completion or do not issue certificates have the "CONSULTA" status and will not show certificate or completion date data.';
$string['search'] = 'Search';

// AJAX error messages
$string['ajax_error'] = 'Error loading data. Please try again.';
$string['ajax_error_detail'] = 'Failed to load data';

// DataTables strings
$string['showing'] = 'Showing';
$string['to'] = 'to';
$string['of'] = 'of';
$string['entries'] = 'entries';
$string['filtered_from'] = 'filtered from';
$string['total'] = 'total';
$string['first'] = 'First';
$string['last'] = 'Last';
$string['next'] = 'Next';
$string['previous'] = 'Previous';