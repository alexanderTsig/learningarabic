(function(pta, $, undefined) {
	pta.dispatchTable['home'] = function(pjax) {
		$('#levels .tile').click(function() {
			window.location = '/level/' +  ($('#levels .tile').index($(this)) + 1);
		}).addClass('clickable');

		$('.tile.stats').click(function() {
			window.location = '/statistics';
		}).addClass('clickable');

		$('.tile.help').click(function() {
			window.location = '/support';
		}).addClass('clickable');

		yepnope({
			load: [
				'/js/avatar/resample.js',
				'/js/avatar/avatar.js'
			],
			complete: function() {
				avatar.init();
			}
		});
	}
}(window.pta = window.pta || {}, jQuery));
