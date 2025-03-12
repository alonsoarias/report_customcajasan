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
 * Spanish language strings for report_customcajasan
 *
 * @package    block_report_customcajasan
 * @copyright  2025 Cajasan
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Nombre del plugin
$string['pluginname'] = 'Informe Personalizado Cajasan';
$string['report_customcajasan'] = 'Informe de Matriculación Cajasan';
$string['enrollment_report'] = 'Informe de Matriculaciones';

// Capacidades
$string['report_customcajasan:addinstance'] = 'Añadir un nuevo bloque de Informe Cajasan';
$string['report_customcajasan:myaddinstance'] = 'Añadir un nuevo bloque de Informe Cajasan a Mi Moodle';
$string['report_customcajasan:viewreport'] = 'Ver Informe de Matriculación Cajasan';

// Cabeceras de tabla - Exactamente como se muestra en la imagen
$string['column_identificacion'] = 'IDENTIFICACIÓN';
$string['column_nombre'] = 'NOMBRE';
$string['column_correo'] = 'CORREO';
$string['column_unidad'] = 'UNIDAD';
$string['column_curso'] = 'CURSO';
$string['column_fecha_matricula'] = 'FECHA DE MATRICULA';
$string['column_fecha_finalizacion'] = 'FECHA DE FINALIZACIÓN';  // Ahora se refiere a la fecha de emisión del certificado
$string['column_estado'] = 'ESTADO';
$string['column_categoria'] = 'CATEGORÍA';

// Opciones de formulario
$string['option_all'] = 'Todos';
$string['option_category'] = 'Categoría';
$string['option_course'] = 'Curso';
$string['option_download_format'] = 'Formato de descarga';
$string['option_download_excel'] = 'Excel';
$string['option_download_ods'] = 'ODS';
$string['option_download_csv'] = 'CSV';

// Otros
$string['report_title'] = 'Informe de Matriculaciones';
$string['btn_download'] = 'Descargar';
$string['total_records'] = 'Total de Registros';
$string['idnumber'] = 'Número de Identificación';
$string['start_date'] = 'Fecha de Inicio';
$string['end_date'] = 'Fecha de Fin';
$string['no_data'] = 'No se encontraron datos de matriculación';
$string['access_denied'] = 'No tienes permiso para ver este informe';
$string['certificate_not_issued'] = 'Certificado no emitido';
$string['certificate_date_note'] = 'En este informe, el campo "FECHA DE FINALIZACIÓN" muestra la fecha de emisión del certificado. Si aparece "No emitido", significa que aún no se ha emitido un certificado para este curso.';
$string['filters_required'] = 'Debe seleccionar al menos un filtro para ver los datos';
$string['select_filter_first'] = 'Por favor, seleccione al menos un filtro para ver los datos del informe';
$string['note_label'] = 'Nota';  // Nueva cadena para la etiqueta "Nota"