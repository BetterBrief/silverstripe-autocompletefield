<?php

/**
 * AutocompleteField
 * Allow the quick selection of items on the client side and (conventionally)
 * return an ID for ease of use.
 * @author Will Morgan <@willmorgan>
 */
class AutocompleteField extends FormField {

	/**
	 * @var callable
	 */
	protected $dataSource;

	/**
	 * @var FieldList
	 */
	protected $children;

	/**
	 * @var string
	 */
	protected $autocompleteID;

	/**
	 * @var array
	 */
	protected $defaultConfig = array(
		/**
		 * This is the key of a datum that will be put into the record field.
		 * To have $this->getRecord() work, it needs to be ID - but you can
		 * customise if you like
		 * @var string
		 */
		'recordIDKey' => 'ID',
		/**
		 * The key in the datum that will be used by the search function
		 * @var string
		 */
		'searchKey' => null,
		/**
		 * The key in the result row that will be displayed. Less relevant if
		 * the template is customised. Defaults to searchKey.
		 * @var string
		 */
		'displayKey' => null,
	);

	/**
	 * @var array
	 */
	protected $requiredConfig = array(
		'searchKey',
	);

	/**
	 * @var array
	 */
	protected $config = array();

	/**
	 * @param string $name
	 * @param string $title
	 * @param callable $dataSource
	 * @param array $userConfig
	 * @param mixed $value
	 */
	public function __construct($name, $title = null, $dataSource, $userConfig = array(), $value = null) {
		$this->setName($name);
		$this->setDataSource($dataSource)->setConfig($userConfig)->setupChildren();
		parent::__construct($name, $title, $value);
	}

	public function setValue($v) {
		if(is_array($v) && isset($v['_RawValue'], $v['_RecordID'])) {
			$this->getRecordIDField()->setValue($v['_RecordID']);
			$this->getRawField()->setValue($v['_RawValue']);
		}
		else if(is_numeric($v)) {
			$this->getRecordIDField()->setValue($v);
			$record = $this->getRecord();
			$this->getRawField()->setValue($record->{$this->config['displayKey']});
		}
		else {
			$this->getRawField()->setValue($v);
		}
		parent::setValue($v);
		return $this;
	}

	/**
	 * @return int|false
	 */
	public function Value() {
		$recordID = $this->getRecordIDField()->Value();
		if(empty($recordID) || !$this->getRecord()) {
			return false;
		}
		return $recordID;
	}

	/**
	 * @return string
	 */
	public function getRawValue() {
		return $this->getRawField()->Value();
	}

	/**
	 * @return DataObject|null
	 */
	public function getRecord() {
		return $this->getLiveData()->byId($this->getRecordIDField()->Value());
	}

	/**
	 * @param array $config
	 * @return $this
	 */
	public function setConfig($config) {
		$this->config = array_merge($this->defaultConfig, $config);
		foreach($this->requiredConfig as $field) {
			if(!isset($this->config[$field])) {
				throw new InvalidArgumentException($field .' is a required configuration key');
			}
		}
		if(!isset($this->config['displayKey'])) {
			$this->config['displayKey'] = $this->config['searchKey'];
		}
		return $this;
	}

	/**
	 * Set up the child elements
	 * @return $this
	 */
	protected function setupChildren() {
		$name = $this->getName();
		$this->children = new FieldList(array(
			TextField::create($name.'[_RawValue]', $this->Title())
				->addExtraClass('autocomplete'),
			HiddenField::create($name.'[_RecordID]')
				->addExtraClass('js-autocomplete-record')
		));
		return $this;
	}

	/**
	 * Include our children in the form too
	 * {@inheritdoc}
	 */
	public function setForm($form) {
		parent::setForm($form);
		foreach($this->getChildren() as $childField) {
			$childField->setForm($form);
		}
		return $this;
	}

	/**
	 * @return FieldList
	 */
	public function getChildren() {
		return $this->children;
	}

	/**
	 * @return TextField
	 */
	public function getRawField() {
		return $this->children->first();
	}

	/**
	 * @return HiddenField
	 */
	public function getRecordIDField() {
		return $this->children->last();
	}

	/**
	 * @param callable $source
	 * @return $this
	 */
	public function setDataSource($source) {
		if(!is_callable($source)) {
			throw new InvalidArgumentException('The data source must be callable');
		}
		$this->dataSource = $source;
		return $this;
	}

	/**
	 * @return callable
	 */
	public function getDataSource() {
		return $this->dataSource;
	}

	/**
	 * Return a DataList of allowed records
	 * @return DataList
	 */
	public function getLiveData() {
		return call_user_func($this->getDataSource());
	}

	/**
	 * Translate the data into an array of arrays
	 * @return array
	 */
	public function getCompleteData() {
		$data = $this->getLiveData();
		if(!isset($data)) {
			throw new InvalidArgumentException(
				'The data source must return an iterable data type'
			);
		}
		// Use toAutocompleteArray to strip out sensitive information like pwds
		if($data instanceof SS_List && $data->hasMethod('toAutocompleteArray')) {
			$data = $data->toAutocompleteArray();
		}
		foreach($data as &$datum) {
			if($datum instanceof DataObject) {
				$datum = $datum->toMap();
			}
		}
		return $data;
	}

	/**
	 * Use this to give a simple name to your autocomplete field, referenced in
	 * the JS file. Only really necessary if you want to customise the defaults.
	 * @param string $id
	 * @return $this
	 */
	public function setAutocompleteID($id) {
		$this->autocompleteID = $id;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getAutocompleteID() {
		return $this->autocompleteID ?: $this->generateAutocompleteID();
	}

	/**
	 * Generate a unique-ish autocomplete ID for referencing in JSconfig
	 * @return string
	 */
	protected function generateAutocompleteID() {
		return str_replace(
			array('[', ']'),
			'_',
			implode('_', array(
				__CLASS__,
				$this->getName()
			))
		);
	}

	/**
	 * @return string
	 */
	public function Field($props = array()) {
		$autocompleteID = $this->getAutocompleteID();
		$jsConfig = $this->config;
		$jsConfig['data'] = $this->getCompleteData();
		JSConfig::add($autocompleteID, $jsConfig);
		Requirements::javascript(AUTOCOMPLETEFIELD_BASE.'/js/autocompletefield.js');
		Requirements::javascript(AUTOCOMPLETEFIELD_BASE.'/vendor/twitter/typeahead.js/dist/typeahead.bundle.min.js');
		$output = array();
		foreach($this->getChildren() as $field) {
			$field->setAttribute('data-autocomplete-id', $autocompleteID);
			$output[] = $field->Field();
		}
		return implode('', $output);
	}

	/**
	 * Add the extra class to our child elements too
	 * {@inheritdoc}
	 */
	public function addExtraClass($class) {
		parent::addExtraClass($class);
		foreach($this->getChildren() as $childField) {
			$childField->addExtraClass($class);
		}
		return $this;
	}

}
