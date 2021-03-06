<?php

/**
 * AutocompleteField
 * Allow the quick selection of items on the client side and (conventionally)
 * return an ID for ease of use.
 * @author Will Morgan <@willmorgan>
 */
class AutocompleteField extends FormField
{

    /**
     * @var callable
     */
    protected $dataSource;

    /**
     * @var callable
     */
    protected $recordFormatter;

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
        /**
         * The key of the result that will be used to populate the raw value box
         * Defaults to searchKey.
         * @var string
         */
        'rawFieldKey' => null,
        /**
         * The kind of field the raw value field is. Use this to take advantage
         * of native field validation for things like emails, numbers, etc.
         * So if no record is found in the auto complete list, the raw value
         * will be validated against the custom field's constraints.
         */
        'rawValueFieldClass' => 'TextField',
        /**
         * Should the data source be cached? Helpful for extensive/expensive
         * lookups. Set to false or0 0 for no cache, or specify a number in
         * seconds for cache length.
         * @var int|boolean
         */
        'cacheDataSource' => 3600,
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
    public function __construct($name, $title = null, $dataSource, $userConfig = array(), $value = null)
    {
        $this->setName($name);
        $this->setDataSource($dataSource)->setConfig($userConfig)->setupChildren();
        parent::__construct($name, $title, $value);
        $this->getRawField()->setTitle($title);
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($v)
    {
        if (is_array($v) && isset($v['_RawValue'], $v['_RecordID'])) {
            $this->getRecordIDField()->setValue($v['_RecordID']);
            $this->getRawField()->setValue($v['_RawValue']);
        } elseif (is_numeric($v)) {
            $this->getRecordIDField()->setValue($v);
            $record = $this->getRecord();
            if ($record) {
                $this->getRawField()->setValue($record->{$this->config['displayKey']});
            }
        } else {
            $this->getRawField()->setValue($v);
        }
        parent::setValue($v);
        return $this;
    }

    /**
     * @return int|false
     */
    public function Value()
    {
        $recordID = $this->getRecordIDField()->Value();
        if (empty($recordID) || !$this->getRecord()) {
            return false;
        }
        return $recordID;
    }

    /**
     * @return string
     */
    public function getRawValue()
    {
        return $this->getRawField()->Value();
    }

    /**
     * @return DataObject|null
     */
    public function getRecord()
    {
        return $this->getLiveData()->byId($this->getRecordIDField()->Value());
    }

    /**
     * @param array $config
     * @return $this
     */
    public function setConfig($config)
    {
        $this->config = array_merge($this->defaultConfig, $config);
        foreach ($this->requiredConfig as $field) {
            if (!isset($this->config[$field])) {
                throw new InvalidArgumentException($field .' is a required configuration key');
            }
        }
        $fallbackKeys = array(
            'displayKey' => 'searchKey',
            'rawFieldKey' => 'searchKey',
        );
        foreach ($fallbackKeys as $key => $fallback) {
            if (!isset($this->config[$key])) {
                $this->config[$key] = $this->config[$fallback];
            }
        }
        return $this;
    }

    /**
     * Set up the child elements
     * @return $this
     */
    protected function setupChildren()
    {
        $name = $this->getName();
        $fieldClass = $this->config['rawValueFieldClass'];
        $this->children = new FieldList(array(
            $fieldClass::create($name.'[_RawValue]', $this->Title())
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
    public function setForm($form)
    {
        parent::setForm($form);
        foreach ($this->getChildren() as $childField) {
            $childField->setForm($form);
        }
        return $this;
    }

    /**
     * @return FieldList
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @return TextField
     */
    public function getRawField()
    {
        return $this->children->first();
    }

    /**
     * @return HiddenField
     */
    public function getRecordIDField()
    {
        return $this->children->last();
    }

    /**
     * @param callable $source
     * @return $this
     */
    public function setDataSource($source)
    {
        if (!is_callable($source)) {
            throw new InvalidArgumentException('The data source must be callable');
        }
        $this->dataSource = $source;
        return $this;
    }

    /**
     * @return callable
     */
    public function getDataSource()
    {
        return $this->dataSource;
    }

    /**
     * Return a DataList of allowed records
     * @return DataList
     */
    public function getLiveData()
    {
        if ($this->config['cacheDataSource']) {
            $cacheID = md5(serialize(array_merge($this->config, array(
                'fieldID' => $this->getAutocompleteID(),
            ))));
            return $this->cacheToFile(
                'generateGetLiveData',
                $this->config['cacheDataSource'],
                $cacheID
            );
        }
        return $this->generateGetLiveData();
    }

    protected function generateGetLiveData()
    {
        return call_user_func($this->getDataSource());
    }

    /**
     * Translate the data into an array of arrays
     * @return array
     */
    public function getCompleteData()
    {
        $data = $this->getLiveData();
        if (!isset($data)) {
            throw new InvalidArgumentException(
                'The data source must return an iterable data type'
            );
        }
        return $this->formatData($data);
    }

    protected function getRecordFormatter()
    {
        return $this->recordFormatter;
    }

    public function setRecordFormatter($formatter)
    {
        if (!is_callable($formatter)) {
            throw new InvalidArgumentException('$formatter must be callable');
        }
        $this->recordFormatter = $formatter;
        return $this;
    }

    /**
     * Format rows to cut down on the data returned to the frontend.
     * @param SS_List $data
     * @return array
     */
    protected function formatData($data)
    {
        $formatter = $this->getRecordFormatter();
        if ($formatter) {
            return call_user_func($formatter, $data);
        }
        // Use toAutocompleteArray to strip out sensitive information like pwds
        if ($data->hasMethod('toAutocompleteArray')) {
            $data = $data->toAutocompleteArray();
        } else {
            $data = $data->toArray();
        }
        foreach ($data as &$datum) {
            if ($datum instanceof DataObject) {
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
    public function setAutocompleteID($id)
    {
        $this->autocompleteID = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getAutocompleteID()
    {
        return $this->autocompleteID ?: $this->generateAutocompleteID();
    }

    /**
     * Generate a unique-ish autocomplete ID for referencing in JSconfig
     * @return string
     */
    protected function generateAutocompleteID()
    {
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
     * {@inheritdoc}
     * If no record is found in the auto complete list, the raw value will be
     * validated against any custom field's constraints.
     */
    public function validate($validator)
    {
        $parentValid = parent::validate($validator);
        // If this field is required but doesn't have a record, then check it
        // at least has a raw value.
        if ($this->Required()) {
            if (!$this->getRecord()) {
                $requiredValid = $this->getRawField()->validate($validator);
                $rawValue = $this->getRawField()->dataValue();
                if (!($requiredValid && !empty($rawValue))) {
                    $validator->validationError(
                        $this->getRawField()->getName(),
                        _t(
                            'AutocompleteField.VALIDATION',
                            '{title} is required',
                            null,
                            array(
                                'title' => $this->getRawField()->Title(),
                            )
                        ),
                        'bad'
                    );
                }
            }
        }
        if (!$this->getRecord()) {
            return $this->getRawField()->validate($validator) && $parentValid;
        }
        return $parentValid;
    }

    /**
     * @return string
     */
    public function Field($props = array())
    {
        $autocompleteID = $this->getAutocompleteID();
        $jsConfig = $this->config;
        $jsConfig['data'] = $this->getCompleteData();
        JSConfig::add($autocompleteID, $jsConfig);
        Requirements::javascript(AUTOCOMPLETEFIELD_BASE.'/js/typeahead.bundle.min.js');
        Requirements::javascript(AUTOCOMPLETEFIELD_BASE.'/js/autocompletefield.js');
        $output = array();
        foreach ($this->getChildren() as $field) {
            $field->setAttribute('data-autocomplete-id', $autocompleteID);
            $output[] = $field->Field();
        }
        return implode('', $output);
    }

    /**
     * Add the extra class to our child elements too
     * {@inheritdoc}
     */
    public function addExtraClass($class)
    {
        parent::addExtraClass($class);
        foreach ($this->getChildren() as $childField) {
            $childField->addExtraClass($class);
        }
        return $this;
    }

    /**
     * Set an attribute on the raw field's input element
     *
     * @param string $name The name of the attribute
     * @param string $value The attribute value
     *
     * @return AutocompleteField the field
     **/
    public function setAttribute($name, $value)
    {
        $this->getRawField()->setAttribute($name, $value);
        return $this;
    }
}
