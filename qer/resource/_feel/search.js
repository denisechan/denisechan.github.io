/*
Creates a search mechanism.

Parameters are a root element whose contents will be searched, and a search bar
in which the user can type their terms.

Elements inside the root element who have the class "search-item" will be hidden or
displayed based on whether they contain any search keywords.

Only elements inside the .search-item element with the class "keywords" will be
checked for keywords.
*/
var Search = function(selector, searchBar) {
	this.root = $(selector).first();
	
	this.searchBar = typeof searchBar === 'undefined' 
		? $('<input type="text" placeholder="search"/>')
		: $(searchBar).first();
	
	var pass = this;
	var changeFunc = function() {
		//Get all the words typed into the search box
		var terms = $(this).val().toLowerCase().split(' ');
		var items = pass.root.find('.search-item');
		
		//No items are matched yet
		var matchedElems = $([]);
		
		if (terms.length !== 0) {
			
			items.each(function() {
				var elem = $(this);
				var keywords = elem.find('.keywords');
				if (keywords.length === 0) keywords = elem;
				
				var bad = false;
				for (var i = 0; i < terms.length; i++) {
					var foundTerm = false;
					keywords.each(function() {
						var text = $(this).text().toLowerCase();
						if (text.indexOf(terms[i]) === -1) bad = true;
						return false;
					});
					if (bad) break;
				}
				
				if (!bad) matchedElems = matchedElems.add(elem);
			});
			
		} else {
			
			matchedItems = items;
			
		}
		
		console.log(matchedElems.length, 'elems');
		
		var unmatchedElems = items.not(matchedElems);
		
		matchedElems.filter('.hidden').each(function() {
			$(this).removeClass('hidden');
			$(this).animate({
				opacity: 1, 
				padding: $(this).data('oldPadding'),
				margin: $(this).data('oldMargin'),
				height: $(this).data('oldHeight')
			}, 200, 'swing', function() { 
				$(this).removeData('oldPadding oldMargin oldHeight oldBorder');
				$(this).css({ opacity: '', padding: '', margin: '', height: '' });
			});
		});
		
		unmatchedElems.filter(':not(.hidden)').each(function() {
			var data = {};
			
			$(this).data({
				oldPadding: $(this).css('padding'),
				oldMargin: $(this).css('margin'),
				oldHeight: $(this).css('height')
			});
			$(this).animate({
				opacity: 0, padding: 0, margin: 0, height: 0
			}, 200, 'swing', function() {
				$(this).addClass('hidden');
			});
		});
	};
	
	this.searchBar.on('keyup', changeFunc);
	this.searchBar.on('change', changeFunc);
};