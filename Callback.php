<?php

if (!defined('_PS_VERSION_'))
  exit;

class Callback extends Module {

	protected $callbackUrl;

	public function __construct() {
		$this->name = 'callback';
		$this->tab = 'Callback after order';
		$this->version = 1.0;
		$this->author = 'http://hpar.fr';
		$this->need_instance = 0;

		$this->callbackUrl = Configuration::updateValue('hpar_callback_url');
		parent::__construct();

		$this->displayName = $this->l('Callback after order');
		$this->description = $this->l('Initiate a callback after actionValidateOrder. JSON in POST');
	}

	public function install() {
		if (!parent::install())
			return false;

		$this->registerHook('actionValidateOrder'); 
		Configuration::updateValue('hpar_callback_url', '');

		return true;
	}

	public function uninstall() {
		Configuration::deleteByName('hpar_callback_url');
		return parent::uninstall();
	}

	public function getContent() {
		$output = null;

		if (Tools::isSubmit('submit'.$this->name))
		{
			$callbackUrl = strval(Tools::getValue('callback_url'));
			if (!$callbackUrl  || empty($callbackUrl) || !Validate::isGenericName($callbackUrl))
				$output .= $this->displayError( $this->l('Invalid Configuration value') );
			else
			{
				Configuration::updateValue('hpar_callback_url', $callbackUrl);
				$output .= $this->displayConfirmation($this->l('Settings updated'));
			}
		}
		return $output.$this->displayForm();
	}

	public function displayForm() {
		// Get default Language
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

		// Init Fields form array
		$fields_form[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('Settings'),
			),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('Callback Url'),
					'name' => 'callback_url',
					'required' => true
				)
			),
			'submit' => array(
				'title' => $this->l('Save'),
				'class' => 'button'
			)
		);

		$helper = new HelperForm();

		// Module, token and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

		// Language
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;

		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar = true;        // false -> remove toolbar
		$helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
			'save' =>
			array(
				'desc' => $this->l('Save'),
				'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
				'&token='.Tools::getAdminTokenLite('AdminModules'),
			),
			'back' => array(
				'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
				'desc' => $this->l('Back to list')
			)
		);

		// Load current value
		$helper->fields_value['callback_url'] = Configuration::get('hpar_callback_url');

		return $helper->generateForm($fields_form);
	}

	public function hookActionValidateOrder($params) {
		//aim : send relevent info the external system 

		//extract all id_customization and build a lookup table
		$customized_dataLookup = $this->lookupCustomized($params['order']->product_list);
		
		//copy only interesting fields (we don't care about all the internal stuff)
		$data = $this->copyInterestingFields($params, $customized_dataLookup);

		//send it to external system
		$this->sendData($data);
		
	}

	protected function lookupCustomized($products) {
		$id_customizations = array();
		$customized_data  = array();

		foreach ($products as $product) {
			$id_customizations[] = $product['id_customization'];
		}

		$s = "SELECT id_customization, value ".
			"FROM `"._DB_PREFIX_."customized_data` ".
			"WHERE `id_customization` IN (".implode(',', $id_customizations).")"; 

		$results = Db::getInstance()->executeS($s);

		//build a lookup table
		foreach ($results as $r) {
			$customized_data[$r['id_customization']] = $r['value'];
		}

		return $customized_data;
	}

	protected function copyInterestingFields($params, $customized_dataLookup) {
		//copy only interesting stuff	
		$out = array();

		$out['order'] = [];

		//things about the order
		$copyFields = array("id_address_delivery","id_address_invoice","id_cart", "payment","module","gift","gift_message", "total_paid", "total_products","total_shipping");
		foreach ($copyFields as $field) {
			$out['order'][$field] = $params['order']->$field;
		}
			
		//things about the products
		$copyFields = array('id','id_product','cart_quantity', 'name', 'price','id_address_delivery','id_customization');
		foreach ($params['order']->product_list as $product) {
			$prod = []; //temp array
			foreach ($copyFields as $field) {
				$prod[$field] = $product[$field];
			}
			$prod['customized_data'] = $customized_dataLookup[$product['id_customization']]; //lookup to add data

			//copy in the out array
			$out['order']['products'][] = $prod;
		}

		//copy only interesting stuff about customer
		$out['customer'] = array(
			'id' => $params['customer']->id,
			'email' => $params['customer']->email);

		return $out;
	}

	protected function sendData($data) {

		$payload = json_encode($data);

		//open connection
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $this->callbackUrl);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

		curl_exec($ch);

		curl_close($ch);

		return true;
	}
}

?>