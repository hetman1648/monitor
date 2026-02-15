function showRow(row_name) {
	$("#" + row_name).toggle();
}
function highlightCell(td, skip) {
	var index = $(td).prevAll().toggleClass("high").length;
	var table = $(td).parent().parent();
	if (skip) {
		var rows  = table.children("tr:gt(" + skip + ")");
	} else {
		var rows  = table.children("tr:gt(0)");
	}
		
	rows.find("td:eq(" + index + ")").toggleClass("high");
	$(td).toggleClass("highCenter");		
}