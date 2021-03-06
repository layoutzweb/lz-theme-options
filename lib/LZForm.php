<?php
namespace Lib;
use Lib\Utils;

/**
 * Class LZForm
 *
 * @package Lib
 */
class LZForm
{

    protected $action, $method, $submit_value, $fields, $sticky, $format, $message_type, $multiple_errors, $html5;
    protected $valid = true;
    protected $name = 'lzto';
    protected $group;
    protected $messages = array();
    protected $data = array();
    protected $formats
        = array(
            'list'  => array(
                'open_form'       => '<ul>',
                'close_form'      => '</ul>',
                'open_form_body'  => '',
                'close_form_body' => '',
                'open_field'      => '',
                'close_field'     => '',
                'open_html'       => "<li>\n",
                'close_html'      => "</li>\n",
                'open_submit'     => "<li>\n",
                'close_submit'    => "</li>\n"
            ),
            'table' => array(
                'open_form'       => '<table>',
                'close_form'      => '</table>',
                'open_form_body'  => '<tbody>',
                'close_form_body' => '</tbody>',
                'open_field'      => "<tr>\n",
                'close_field'     => "</tr>\n",
                'open_html'       => "<td>\n",
                'close_html'      => "</td>\n",
                'open_submit'     => '<tfoot><tr><td>',
                'close_submit'    => '</td></tr></tfoot>'
            )
        );
    protected $base_path;
    protected $descriptions= array();
    private static $instance = array();

    /**
     * LzForm Construct
     *
     * @param $action
     * @param $submit_value
     * @param $html5
     * @param $method
     * @param $sticky
     * @param $message_type
     * @param $format
     * @param $multiple_errors
     */
    public function __construct(
    	//$name,
        $action,
        $submit_value,
        $html5,
        $method,
        $sticky,
        $message_type,
        $format,
        $multiple_errors
    ) {
    	//$this->name = $name;
    	$this->base_path = dirname(__FILE__) . DIRECTORY_SEPARATOR;
        $this->fields = new \stdClass();
        $this->action = $action;
        $this->method = $method;
        $this->html5 = $html5;
        $this->submit_value = $submit_value;
        $this->sticky = $sticky;
        $this->format = $format;
        $this->message_type = $message_type;
        $this->multiple_errors = $multiple_errors;
    }

    /**
     * Singleton method
     *
     * @param string  $action
     * @param string  $method
     * @param boolean $sticky
     * @param string  $submit_value
     * @param string  $message_type
     * @param string  $format
     * @param string  $multiple_errors
     *
     * @return LZForm
     */
    public static function getInstance(
        $name = '',
        $action = '',
        $html5 = true,
        $method = 'post',
        $submit_value = 'Submit',
        $format = 'list',
        $sticky = true,
        $message_type = 'list',
        $multiple_errors = false
    ) {
        if (!isset(self::$instance[$name])) {
            self::$instance[$name]
                = new LZForm($action, $submit_value, $html5, $method, $sticky, $message_type, $format, $multiple_errors);
        }

        return self::$instance[$name];
    }

    /**
     * Return all form instances
     *
     * @return array
     */
    public function getAllInstances(){
    	return self::$instance;
    }

    /**
     * Add a field to the form instance
     *
     * @param string  $field_name
     * @param string  $type
     * @param array   $attributes
     * @param boolean $overwrite
     *
     * @return boolean
     */
    public function addField($field_name, $type = 'text', array $attributes = array(), $overwrite = false)
    {
        $namespace = "Lib\\Field\\" . ucfirst($type);

        if (isset($attributes['label'])) {
            $label = $attributes['label'];
        } else {
            $label = ucfirst(str_replace('_', ' ', $field_name));
        }

        $field_name = Utils::slugify($field_name, '_');

        if (isset($this->fields->$field_name) && !$overwrite) {
            return false;
        }

        $label = isset($attributes['label']) ? $attributes['label'] : '';
        $this->fields->$field_name = $this->getFieldTypeInstance( $type, $label, $attributes );

        if( false == $this->fields->$field_name ){
        	return false;
        }

        $this->fields->$field_name->setForm($this);

        return $this->fields->$field_name;
    }

    /**
     * Add a description to the field
     *
     * @param $field
     * @param $description
     */
    public function addDescription( $field, $description ){
    	$this->descriptions[$field] = $description;
    }

    /**
     * Return a instance of a field given it's properties
     *
     * @param $type
     * @param $label
     * @param array $attributes
     * @return bool
     */
    protected function getFieldTypeInstance( $type, $label, $attributes = array() ){
    	$file = $this->base_path.'Field'.DIRECTORY_SEPARATOR.ucfirst($type).'.php';
    	$namespace = "Lib\\Field\\" . ucfirst($type);
    	if( file_exists( $file ) ){
    		if( !class_exists($namespace) ){
    			require $file;
    		}
    		return new $namespace($label, $attributes);
    	}
    	return false;
    }

    /**
     * Set the name of the form
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get form name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get form method
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Set group name
     *
     * @param $group
     */
    public function setGroup($group){
    	$this->group = $group;
    }

    /**
     * Return group name
     *
     * @return mixed
     */
    public function getGroup(){
    	return $this->group;
    }

    /**
     * Add data to populate the form
     *
     * @param array $data
     */
    public function addData(array $data){
        $this->data = array_merge($this->data, $data);
    }

    /**
     * Return current form data
     *
     * @return array
     */
    public function getData(){
    	return $this->data;
    }

    /**
     * Validate the submitted form
     *
     * @return boolean
     */
    public function validate( $form_name, $returnData = false )
    {
    	$errors = array();
        $request = \Params::getParam($this->name);
        $un_request = \Params::getParam($this->name, false, false);

        $form_data = array();
        $un_form_data = array();
        if (isset($request[$form_name])) {
            $form_data = array_filter( $request[$form_name]);
            $un_form_data = array_filter( $un_request[$form_name]);
        } /*else {
            $this->valid = false;
            return false;
        }*/

        if ($this->sticky) {
            $this->addData($form_data);
        }

        foreach ($this->fields as $key => $field ) {

            $real_value = (isset($form_data[$key])? $form_data[$key] : (isset($_FILES[$form_name][$key]) ? $_FILES[$form_name][$key] : '' ) );
            $filter = $field->getAttribute('filter');

            if( $filter !== -1 ){
                $filter = (bool)$filter;
            } else {
                $filter = true;
            }

            if( !$filter && isset($un_form_data[$key]) ){
                $real_value = $un_form_data[$key];
                $form_data[$key] = $real_value;
                if($this->sticky){
                    $this->data[$key] = $real_value;
                }
            }

            if( empty($real_value) && !$field->isRequired() ){

            } else {
                if ( !$field->validate( $real_value ) ) {
                    $this->valid = false;
                    return false;
                }
            }
        }

        return ( false !== $returnData )? $form_data : $this->valid;
    }

    /**
     * Render the entire form including submit button, errors, form tags etc
     *
     * @return string
     */
    public function render()
    {
        $fields = '';
        $error = $this->valid ? ''
            : '<p class="error">Sorry there were some errors in the form, problem fields have been highlighted</p>';
        $format = (object) $this->formats[$this->format];
        $this->setToken();

        foreach ($this->fields as $key => $value) {
            $format = (object) $this->formats[$this->format];
            $temp = isset($this->data[$key]) ? $value->returnField($this->name, $key, $this->data[$key])
                : $value->returnField($this->name, $key);
            $fields .= $format->open_field;
            if ($temp['label']) {
                $fields .= $format->open_html . $temp['label'] . $format->close_html;
            }
            if (isset($temp['messages'])) {
                foreach ($temp['messages'] as $message) {
                    if ($this->message_type == 'inline') {
                        $fields .= "$format->open_html <p class=\"error\">$message</p> $format->close_html";
                    } else {
                        $this->setMessages($message, $key);
                    }
                    if (!$this->multiple_errors) {
                        break;
                    }
                }
            }
            $fields .= $format->open_html . $temp['field'] . $format->close_html . $format->close_field;
        }

        if (!empty($this->messages)) {
            $this->buildMessages();
        } else {
            $this->messages = false;
        }
        self::$instance = false;
        $attributes = $this->getFormAttributes();

        return <<<FORM
            $error
            $this->messages
            <form class="form" role="form" action="$this->action" method="$this->method" {$attributes['enctype']} {$attributes['html5']}>
              $format->open_form
                $format->open_form_body
                  $fields
                $format->close_form_body
                $format->open_submit
                  <input type="submit" name="submit" value="$this->submit_value" />
                $format->close_submit
              $format->close_form
            </form>
FORM;
    }

    /**
     * Returns the HTML for a specific form field ususally in the form of input tags
     *
     * @param string $name
     * @return string
     */
    public function renderField($name)
    {
        return $this->getFieldData($name, 'field');
    }

    /**
     * Returns the HTML for a specific form field's label
     *
     * @param string $name
     * @return string
     */
    public function renderLabel($name)
    {
        return $this->getFieldData($name, 'label');
    }

    /**
     * Returns the error string for a specific form field
     *
     * @param string $name
     * @return string
     */
    public function renderError($name)
    {
        $error_string = '';
        if (!is_array($this->getFieldData($name, 'messages'))) {
            return false;
        }
        foreach ($this->getFieldData($name, 'messages') as $error) {
            $error_string .= "<li>$error</li>";
        }

        return $error_string === '' ? false : "<ul>$error_string</ul>";
    }

    /**
     * Returns the boolean depending on existance of errors for specified
     * form field
     *
     * @param string $name
     * @return boolean
     */
    public function hasError($name)
    {
        $errors = $this->getFieldData($name, 'messages');
        if (!$errors || !is_array($errors)) {
            return false;
        }

        return true;
    }

    /**
     * Returns the entire HTML structure for a form field
     *
     * @param string $name
     * @return string
     */
    public function renderRow($name)
    {
    	$row_string = '';
    	switch( $this->fields->$name->field_type ){
    		case 'fieldGroup':
    			$row_string .= 		$this->renderField($name);
    			break;
    		default:
    			$row_string .= '<div class="form-group '.$this->fields->$name->field_type.'">';
    			if( isset( $this->descriptions[$name] ) ){
    				$row_string .=   '<span class="description" data-field="'.osc_esc_html($name).'" data-description="'.osc_esc_html($this->descriptions[$name]).'"></span>';
    			}
    			$row_string .= 		$this->renderLabel($name);
    			$row_string .= 		$this->renderField($name);
    			$row_string .= 		$this->renderError($name);
    			$row_string .= '</div>';
    			break;
    	}
        return $row_string;
    }

    /**
     * Returns HTML for all hidden fields including crsf protection
     *
     * @return string
     */
    public function renderHidden()
    {
        $this->setToken();
        $fields = array();
        foreach ($this->fields as $name => $field) {
            if (get_class($field) == 'Lib\\Field\\Hidden') {
                if (isset($this->data[$name])) {
                    $field_data = $field->returnField($this->name, $name, $this->data[$name]);
                } else {
                    $field_data = $field->returnField($this->name, $name);
                }
                $fields[] = $field_data['field'];
            }
        }

        return implode("\n", $fields);
    }

    /**
     * Returns HTML string for all errors in the form
     *
     * @return string
     */
    public function renderErrors()
    {
        $error_string = '';
        foreach ($this->fields as $name => $value ) {
            foreach ($this->getFieldData($name, 'messages') as $error) {
                $error_string .= "<li>$name $error</li>\n";
            }
        }

        return $error_string === '' ? false : "<ul>$error_string</ul>";
    }

    /**
     * Return current form errors
     *
     * @return array|bool
     */
    public function getErrors(){
    	$errors = array();
    	foreach ( $this->fields as $name => $value ) {
    		$messages = $this->getFieldData( $name, 'error' );
			if( !empty($messages) ){
	    		foreach ( $messages as $error) {
	    			$errors[$name] .= $error;
	    		}
			}
    	}
    	return empty($errors) ? false : $errors;
    }

    /**
     * Returns the HTML string for opening a form with the correct enctype, action and method
     *
     * @return string
     */
    public function openForm()
    {
        $attributes = $this->getFormAttributes();

        return "<form class=\"form\" action=\"$this->action\" method=\"$this->method\" {$attributes['enctype']} {$attributes['html5']}>";
    }

    /**
     * Return close form tag
     *
     * @return string
     */
    public function closeForm()
    {
        return "</form>";
    }

    /**
     * Check if a field exists
     *
     * @param string $field
     * @return boolean
     */
    public function checkField($field)
    {
        return isset($this->fields->$field);
    }

    /**
     * Return an instance a a field or false
     *
     * @param $name
     * @return bool
     */
    public function getField($name){
        if( $this->checkField($name)){
            return $this->fields->$name;
        }
        return false;
    }

    /**
     * Return how many fields are in this form
     *
     * @return int
     */
    public function countFields(){
    	return count($this->fields);
    }

    /**
     * Get the attributes for the form tag
     *
     * @return array
     */
    private function getFormAttributes()
    {
        $enctype = '';
        foreach ($this->fields as $field) {
            if (get_class($field) == 'File') {
                $enctype = 'enctype="multipart/form-data"';
            }
        }
        $html5 = $this->html5 ? '' : 'novalidate';

        return array(
            'enctype' => $enctype,
            'html5'   => $html5
        );
    }

    /**
     * Adds a message string to the class messages array
     *
     * @param string $message
     * @param string $title
     */
    private function setMessages($message, $title)
    {
        $title = preg_replace('/_/', ' ', ucfirst($title));
        if ($this->message_type == 'list') {
            $this->messages[] = array('title' => $title, 'message' => ucfirst($message));
        }
    }

    /**
     * Sets the messages array as an HTML string
     */
    private function buildMessages()
    {
        $messages = '<ul class="error">';
        foreach ($this->messages as $message_array) {
            $messages .= sprintf(
                '<li>%s: %s</li>%s',
                ucfirst(preg_replace('/_/', ' ', $message_array['title'])),
                ucfirst($message_array['message']),
                "\n"
            );
        }
        $this->messages = $messages . '</ul>';
    }

    /**
     * Gets a specific field HTML string from the field class
     *
     * @param string $name
     * @param string $key
     *
     * @return string
     */
    private function getFieldData($name, $key)
    {
        if (!$this->checkField($name)) {
            return false;
        }
        $field = $this->fields->$name;
        if (isset($this->data[$name])) {
            $field = $field->returnField($this->name, $name, $this->data[$name], $this->group );
        } else {
            $field = $field->returnField($this->name, $name, '', $this->group );
        }
        return $field[$key];
    }

    /**
     * Returns the value of a specific field
     *
     * @param $field
     * @return string
     */
    public function getFieldValue( $field ){
    	return ( isset( $this->data[$field] ) )? $this->data[$field] : '';
    }


}

