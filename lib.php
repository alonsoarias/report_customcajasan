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
 * Library functions for report_customcajasan
 *
 * @package    block_report_customcajasan
 * @copyright  2025 Cajasan
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/excellib.class.php');
require_once($CFG->libdir . '/odslib.class.php');
require_once($CFG->libdir . '/csvlib.class.php');

/**
 * Get enrollment data based on filters
 *
 * @param array $filters Filter parameters
 * @return array Enrollment data
 */
function report_customcajasan_get_data($filters) {
    global $DB;

    // Verificamos si existen las tablas relacionadas con certificados y finalización
    $dbman = $DB->get_manager();
    $cert_table_exists = $dbman->table_exists('customcert_issues');
    $completion_table_exists = $dbman->table_exists('course_completions');
    
    // Base query con la información básica de matriculación
    $sql = "SELECT 
                CONCAT(u.id, '_', c.id) AS unique_id,
                u.id AS userid,
                c.id AS courseid,
                u.idnumber AS identificacion,
                u.firstname AS nombres,
                u.lastname AS apellidos,
                u.email AS correo,
                u.department AS unidad,
                c.fullname AS curso,
                FROM_UNIXTIME(ue.timestart) AS fecha_matricula,";
    
    // Construcción condicional para verificar si hay certificado disponible para el curso
    if ($cert_table_exists) {
        $sql .= "CASE WHEN cert.id IS NOT NULL THEN 1 ELSE 0 END AS has_certificate,";
    } else {
        $sql .= "0 AS has_certificate,";
    }
    
    // Construcción condicional para fecha de certificado
    if ($cert_table_exists) {
        $sql .= "CASE
                    WHEN ci.timecreated IS NOT NULL THEN FROM_UNIXTIME(ci.timecreated) 
                    ELSE NULL
                END AS fecha_certificado,";
    } else {
        $sql .= "NULL AS fecha_certificado,";
    }
    
    // Construcción condicional para fecha de finalización
    if ($completion_table_exists) {
        $sql .= "CASE
                    WHEN cc.timecompleted IS NOT NULL THEN FROM_UNIXTIME(cc.timecompleted)
                    ELSE NULL
                END AS fecha_finalizacion,";
    } else {
        $sql .= "NULL AS fecha_finalizacion,";
    }
    
    // Determinación del estado según los requisitos - con lógica mejorada
    $sql .= "CASE 
                WHEN (ci.id IS NOT NULL) THEN 'COMPLETO'
                WHEN (cc.timecompleted IS NOT NULL) THEN 'FINALIZADO'
                WHEN (l.id IS NOT NULL OR cc.timestarted IS NOT NULL) THEN 'EN PROGRESO'
                WHEN (cert.id IS NULL AND (cm.completion = 0 OR cm.completion IS NULL)) THEN 'CONSULTA'
                ELSE 'CONSULTA'
            END AS estado,";
    
    $sql .= "cat.name AS categoria
            FROM {user_enrolments} ue
            JOIN {enrol} e ON ue.enrolid = e.id
            JOIN {course} c ON e.courseid = c.id
            JOIN {course_categories} cat ON c.category = cat.id
            JOIN {user} u ON ue.userid = u.id
            LEFT JOIN (
                SELECT course, COUNT(*) as has_completion
                FROM {course_modules}
                WHERE completion > 0
                GROUP BY course
            ) hc ON hc.course = c.id
            LEFT JOIN {course_modules} cm ON cm.course = c.id
            LEFT JOIN {logstore_standard_log} l ON l.userid = u.id AND l.courseid = c.id";
    
    // JOIN condicional para completamiento si la tabla existe
    if ($completion_table_exists) {
        $sql .= " LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id";
    }
    
    // JOIN condicional para certificados si la tabla existe
    if ($cert_table_exists) {
        // Unión con la tabla de certificados (para verificar si el curso tiene certificado)
        $sql .= " LEFT JOIN {customcert} cert ON cert.course = c.id";
        // Unión con la tabla de emisiones de certificados (para ver si este usuario tiene certificado emitido)
        $sql .= " LEFT JOIN {customcert_issues} ci ON ci.userid = u.id AND ci.customcertid = cert.id";
    }
    
    $sql .= " WHERE u.deleted = 0";
    
    $params = array();
    
    // Aplicar filtro de categoría
    if (!empty($filters['category'])) {
        $sql .= " AND c.category = :category";
        $params['category'] = $filters['category'];
    }
    
    // Aplicar filtro de curso
    if (!empty($filters['course'])) {
        $sql .= " AND c.id = :course";
        $params['course'] = $filters['course'];
    }
    
    // Aplicar filtro de número de identificación
    if (!empty($filters['idnumber'])) {
        $sql .= " AND u.idnumber LIKE :idnumber";
        $params['idnumber'] = '%' . $filters['idnumber'] . '%';
    }
    
    // Aplicar filtro de nombre
    if (!empty($filters['firstname'])) {
        $sql .= " AND u.firstname LIKE :firstname";
        $params['firstname'] = $filters['firstname'] . '%';
    }
    
    // Aplicar filtro de apellido
    if (!empty($filters['lastname'])) {
        $sql .= " AND u.lastname LIKE :lastname";
        $params['lastname'] = $filters['lastname'] . '%';
    }
    
    // Aplicar filtro de estado - Actualizado para coincidir con lógica de estados
    if (!empty($filters['estado'])) {
        if ($filters['estado'] === 'COMPLETO') {
            $sql .= " AND ci.id IS NOT NULL";
        } else if ($filters['estado'] === 'FINALIZADO') {
            $sql .= " AND cc.timecompleted IS NOT NULL AND ci.id IS NULL";
        } else if ($filters['estado'] === 'EN PROGRESO') {
            $sql .= " AND (l.id IS NOT NULL OR cc.timestarted IS NOT NULL) AND cc.timecompleted IS NULL AND ci.id IS NULL";
        } else if ($filters['estado'] === 'CONSULTA') {
            if ($cert_table_exists) {
                $sql .= " AND cert.id IS NULL AND (cm.completion = 0 OR cm.completion IS NULL)";
            } else {
                $sql .= " AND (cm.completion = 0 OR cm.completion IS NULL)";
            }
            $sql .= " AND ci.id IS NULL AND cc.timecompleted IS NULL AND l.id IS NULL AND (cc.timestarted IS NULL OR cc.timestarted = 0)";
        }
    }
    
    // Aplicar filtros de fecha
    if (!empty($filters['startdate'])) {
        $sql .= " AND ue.timestart >= :startdate";
        $params['startdate'] = $filters['startdate'];
    }
    
    if (!empty($filters['enddate'])) {
        $sql .= " AND ue.timestart <= :enddate";
        $params['enddate'] = $filters['enddate'];
    }
    
    // Agrupar para evitar duplicados
    $sql .= " GROUP BY u.id, c.id, cat.name";
    if ($cert_table_exists) {
        $sql .= ", cert.id, ci.id, ci.timecreated";
    }
    if ($completion_table_exists) {
        $sql .= ", cc.timecompleted, cc.timestarted";
    }
    
    // Ordenar por
    $sql .= " ORDER BY u.lastname, u.firstname, c.fullname";
    
    return $DB->get_records_sql($sql, $params);
}

/**
 * Get the list of available states for filtering
 *
 * @return array List of states
 */
function report_customcajasan_get_states() {
    return [
        '' => get_string('option_all', 'block_report_customcajasan'),
        'COMPLETO' => get_string('state_completo', 'block_report_customcajasan'),
        'EN PROGRESO' => get_string('state_enprogreso', 'block_report_customcajasan'),
        'FINALIZADO' => get_string('state_finalizado', 'block_report_customcajasan'),
        'CONSULTA' => get_string('state_consulta', 'block_report_customcajasan')
    ];
}

/**
 * Export data to .xlsx or .ods format.
 *
 * @param array $headers The headers for the data.
 * @param array $data The rows of data.
 * @param string $filename The base name of the file without extension.
 * @param string $format The format of the export: 'excel' or 'ods'.
 * @param string $sheetname The name of the worksheet.
 */
function report_customcajasan_export_spreadsheet($headers, $data, $filename, $format, $sheetname = 'Sheet1')
{
    global $CFG;

    // Clear output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    if ($format === 'excel') {
        $workbook = new MoodleExcelWorkbook("-");
        $filename .= '.xlsx';
    } else {
        $workbook = new MoodleODSWorkbook("-");
        $filename .= '.ods';
    }

    $workbook->send($filename);
    $worksheet = $workbook->add_worksheet($sheetname);

    // Set column widths
    $worksheet->set_column(0, 0, 15);  // Identificación
    $worksheet->set_column(1, 1, 20);  // Nombres
    $worksheet->set_column(2, 2, 20);  // Apellidos
    $worksheet->set_column(3, 3, 30);  // Correo
    $worksheet->set_column(4, 4, 20);  // Unidad
    $worksheet->set_column(5, 5, 40);  // Curso
    $worksheet->set_column(6, 6, 20);  // Fecha de matricula
    $worksheet->set_column(7, 7, 20);  // Fecha de emisión certificado
    $worksheet->set_column(8, 8, 20);  // Fecha finalización curso
    $worksheet->set_column(9, 9, 15);  // Estado
    $worksheet->set_column(10, 10, 30);  // Categoría

    // Header format
    $headerformat = $workbook->add_format();
    $headerformat->set_bold();
    $headerformat->set_align('center');
    $headerformat->set_align('vcenter');
    $headerformat->set_bg_color('#CCCCCC');

    // Write headers
    for ($i = 0; $i < count($headers); $i++) {
        $worksheet->write(0, $i, $headers[$i], $headerformat);
    }

    // Write data
    $row = 1;
    foreach ($data as $record) {
        $col = 0;
        foreach ($record as $value) {
            $worksheet->write($row, $col++, $value);
        }
        $row++;
    }

    $workbook->close();
    exit;
}

/**
 * Export data to CSV format.
 *
 * @param array $headers The headers for the data.
 * @param array $data The rows of data.
 * @param string $filename The base name of the file without extension.
 */
function report_customcajasan_export_csv($headers, $data, $filename) {
    // Clear output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename .= '.csv';
    
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Pragma: public");

    $output = fopen('php://output', 'w');
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data
    foreach ($data as $record) {
        fputcsv($output, $record);
    }
    
    fclose($output);
    exit;
}

/**
 * Get all course categories
 *
 * @return array List of categories
 */
function report_customcajasan_get_categories() {
    global $DB;
    
    return $DB->get_records('course_categories', null, 'name ASC', 'id, name, depth, path');
}

/**
 * Get courses by category
 *
 * @param int $categoryid Category ID
 * @return array List of courses
 */
function report_customcajasan_get_courses($categoryid) {
    global $DB;
    
    $params = array();
    $sql = "SELECT id, fullname FROM {course} WHERE 1=1";
    
    if (!empty($categoryid)) {
        $sql .= " AND category = :category";
        $params['category'] = $categoryid;
    }
    
    $sql .= " ORDER BY fullname ASC";
    
    return $DB->get_records_sql($sql, $params);
}