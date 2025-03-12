/**
 * JavaScript for the report_customcajasan report
 *
 * @module     block_report_customcajasan/report
 * @copyright  2025 Cajasan
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    /**
     * Initialize the module
     */
    function init() {
        // Handle category change - update courses
        $('#categoryid').on('change', function() {
            var categoryId = $(this).val();
            
            // Clear course selection
            $('#courseid').empty();
            $('#courseid').append($('<option>', {
                value: '',
                text: M.util.get_string('option_all', 'block_report_customcajasan')
            }));
            
            if (categoryId) {
                // Get courses for the selected category
                $.ajax({
                    url: M.cfg.wwwroot + '/blocks/report_customcajasan/ajax/get_courses.php',
                    type: 'GET',
                    data: {
                        'categoryid': categoryId,
                        'sesskey': M.cfg.sesskey
                    },
                    dataType: 'json',
                    success: function(data) {
                        if (data.success && data.courses) {
                            $.each(data.courses, function(index, course) {
                                $('#courseid').append($('<option>', {
                                    value: course.id,
                                    text: course.fullname
                                }));
                            });
                        }
                    },
                    error: function() {
                        // Just use the default empty selection in case of error
                        require(['core/log'], function(log) {
                            log.debug('Error loading courses.');
                        });
                    }
                });
            }
        });
        
        // Initialize datatable if library is available
        if ($.fn.DataTable) {
            $('#enrollment-report-table').DataTable({
                "paging": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "responsive": true,
                "pageLength": 25,
                "language": {
                    "search": M.util.get_string('search'),
                    "lengthMenu": M.util.get_string('show') +
                        " _MENU_ " + M.util.get_string('entries'),
                    "info": M.util.get_string('showing') +
                        " _START_ " + M.util.get_string('to') +
                        " _END_ " + M.util.get_string('of') +
                        " _TOTAL_ " + M.util.get_string('entries'),
                    "infoEmpty": M.util.get_string('showing') +
                        " 0 " + M.util.get_string('to') +
                        " 0 " + M.util.get_string('of') +
                        " 0 " + M.util.get_string('entries'),
                    "infoFiltered": "(" + M.util.get_string('filtered_from') +
                        " _MAX_ " + M.util.get_string('total') +
                        " " + M.util.get_string('entries') + ")",
                    "paginate": {
                        "first": M.util.get_string('first'),
                        "last": M.util.get_string('last'),
                        "next": M.util.get_string('next'),
                        "previous": M.util.get_string('previous')
                    }
                }
            });
        }
    }
    
    return {
        init: init
    };
});