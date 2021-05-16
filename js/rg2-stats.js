$(document).ready( function () {
	$('#rg2-sites-table').DataTable({
		'autoWidth': true,
		'order': [ 2, 'desc' ],
		'searching': false,
  "columnDefs": [
    { className: "dt-center", "targets": [1, 2, 3, 4, 5] },
    { "width": "10%", "targets": 7 },
    { "orderable": false, "targets": 8 }
  ]
	});

	$('#rg2-events-table').DataTable({
		'autoWidth': true,
		'order': [ 0, 'desc' ],
		'searching': false,
  "columnDefs": [
    { className: "dt-center", "targets": [4, 5, 6, 7] },
    { "width": "10%", "targets": 0 }
  ]		
	});
});
