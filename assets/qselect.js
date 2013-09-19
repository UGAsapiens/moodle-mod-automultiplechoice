/* 
 * @license http://www.gnu.org/licenses/gpl-2.0.html  GNU GPL v2
 */

$(document).ready(function() {
	var dataTableConfig = {
		"iDisplayLength": 20,
		"bPaginate": true,
		"aaSorting": [], // no initial sorting
		"aoColumnDefs": [
			{ "aTargets": [ 2 ], "sWidth": "12ex" },
			{ "aTargets": [ 3 ], "bSortable": false, "sWidth": "5ex" }
		],
		"oLanguage": {}
	};
    $("#questions-list").dataTable(dataTableConfig);

    $("#questions-selected").sortable();

    var Question = {
        template: null,
        initTemplate: function() {
            this.template = $("#questions-selected > li").first().clone();
            this.template.removeAttr("style");
			this.template.find('input').removeAttr('disabled');
        },
        add: function(qid, qtitle) {
            var chunk = this.template.clone();
            chunk.find("input").first().val(qid);
            chunk.find("label").first().html(qtitle);
			chunk.attr("id", "qsel-" + qid);
            chunk.appendTo($("#questions-selected"));
        },
        remove: function(qid) {
            $("#qsel-" + qid).remove();
        }
    };
	Question.initTemplate();

    $("#questions-list").on("click", "button", function(e) {
		var bton = $(e.target);
		var qid = $(e.target).data("qid");
        if (bton.data("selected")) {
			bton.text("+");
            bton.removeData("selected");
            Question.remove(qid);
        } else {
			var qtitle = bton.closest('tr').children('td.qtitle').first().text();
			bton.text("-");
            bton.data("selected", true);
            Question.add(qid, qtitle);
        }
    });
    $("#questions-selected").on("click", "button", function(e) {
        var qid = $(this).closest('li').find('input').first().val();
		Question.remove(qid);
    });
} );
