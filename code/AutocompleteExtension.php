<?php

/**
 * AutocompleteExtension
 * @author Will Morgan <@willmorgan>
 */
class AutocompleteExtension extends DataExtension {

	/**
	 * @return array
	 */
	public function toAutocompleteArray() {
		return static::to_autocomplete_array($this->owner);
	}

	/**
	 * @param SS_List $scope the scope to iterate over - handy if you don't want
	 * to add this extension for a one-off use
	 * @return array
	 */
	static public function to_autocomplete_array($scope) {
		$items = $scope->toArray();
		foreach($items as &$item) {
			if($item->hasMethod('toAutocompleteMap')) {
				$item = $item->toAutocompleteMap();
			}
			else {
				$item = $item->toMap();
			}
		}
		return $items;
	}

}
