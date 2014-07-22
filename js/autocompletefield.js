(function($) {
	RegExp.escape= function(s) {
		return s.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
	};
	var AutocompleteField = window.AutocompleteField || {};
	$.extend(true, AutocompleteField, {
		// matchers for finding stuff in a dataset
		matchers: {
			_default: function(config) {
				return function(q, cb) {
					var matches = [],
						dataSet = config.data,
						tokenized = q.split(' '),
						substrRegex = [],
						i, j;
					for(i in tokenized) {
						if(tokenized[i].length) {
							substrRegex.push(new RegExp(RegExp.escape(tokenized[i]), 'i'));
						}
					}

					for(i in dataSet) {
						match:
						for(j in substrRegex) {
							if(substrRegex[j].test(dataSet[i][config.searchKey])) {
								matches.push(dataSet[i]);
								break match;
							}
						}
					}
					return cb(matches);
				}
			}
		},
		// options for the first parameter of $.typeahead
		typeaheadOptions: {
			_default: {
				hint: true,
				highlight: false,
				minLength: 1
			}
		},
		// options for the second parameter of $.typeahead
		datasetOptions: {
			_default: function(config) {
				return {
					source: AutocompleteField.matchers._default(config),
					displayKey: config.rawFieldKey,
					templates: {
						empty: '<div class="empty-message">No results</div>',
						suggestion: function(suggestion) {
							return '<p>'+suggestion[config.displayKey]+'</p>';
						}
					}
				}
			}
		},
		getOption: function(kind, fieldID) {
			if(fieldID in this[kind]) {
				return this[kind][fieldID];
			}
			return this[kind]._default;
		},
		// set up typeahead on a document fragment
		initialise: function(fragment) {
			fragment = fragment || document.body;
			var $inputs = $('input.autocomplete', fragment);
			$inputs.each(function() {
				var $input = $(this);
				var autocompleteID = $input.attr('data-autocomplete-id');
				var config = JSCONFIG[autocompleteID];
				var $recordField = $('.js-autocomplete-record[data-autocomplete-id='+autocompleteID+']', fragment);
				// Set up typeahead
				$input.typeahead(
					AutocompleteField.getOption('typeaheadOptions', autocompleteID),
					AutocompleteField.getOption('datasetOptions', autocompleteID)(config)
				);
				// on complete, set the hidden field's value to record.ID
				$input.on('typeahead:autocompleted typeahead:selected', function(ev, record) {
					$recordField.val(record[config.recordIDKey]).trigger('change');
					$input.trigger('autocompletefield:selected');
				});
				// for all other events, set it to blank
				$input.on('keyup', function(ev) {
					var keycode = ev.keyCode;
					if(
						(keycode > 47 && keycode < 58)   || // number keys
						(keycode > 64 && keycode < 91)   || // letter keys
						(keycode > 95 && keycode < 112)  || // numpad keys
						(keycode > 185 && keycode < 193) || // ;=,-./` (in order)
						(keycode > 218 && keycode < 223)    // [\]' (in order)
					) {
						$recordField.val('').trigger('change');
					}
				});
			});
		}
	});
	window.AutocompleteField = AutocompleteField;
	$(document).ready(function() {
		AutocompleteField.initialise(document.body);
	});
}(jQuery));
