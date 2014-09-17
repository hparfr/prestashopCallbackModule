<?php

if (!defined('_PS_VERSION_'))
  exit;

class hparCallback extends Module {

	public function __construct() {
		$this->name = 'hparCallback';
		$this->tab = 'Callback after order';
		$this->version = 1.2;
		$this->author = 'http://hpar.fr';
		$this->need_instance = 0;

		parent::__construct();

		$this->displayName = $this->l('Callback after order');
		$this->description = $this->l('Initiate a callback after actionValidateOrder. JSON in POST');
	}

	public function install() {
		if (!defined('_HPAR_CALLBACK_URL_')) {
			$this->_errors[] = $this->l('You need to add _HPAR_CALLBACK_URL_ constant in settings.config.php');
			return false;
		}

		if (!parent::install())
			return false;

		$this->registerHook('actionValidateOrder'); 

		return true;
	}

	public function uninstall() {
		return parent::uninstall();
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
		$copyFields = array('id_product','cart_quantity', 'name', 'price','id_address_delivery','id_customization', 'reference');
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

		curl_setopt($ch, CURLOPT_URL, _HPAR_CALLBACK_URL_);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

		curl_exec($ch);

		curl_close($ch);

		return true;
	}
}

?>