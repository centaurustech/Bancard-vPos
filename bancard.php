<?php
class Bancard_model extends CI_Model {
	
	var $publicKey;
	var $privateKey;
	var $token;
	var $url;
	
    function __construct()
    {
	global $DB;
        parent::__construct();
        $this->db = $DB;										// SIMPLEPDO INSTANCE	
        $this->createTable();									
		
		$this->publicKey = "";									// BANCARD PUBLIC KEY
		$this->privateKey = "";									// BANCARD PRIVATE KEY
		
		// $this->url = "https://vpos.infonet.com.py";			// PRODUCTION
		$this->url = "https://vpos.infonet.com.py:8888";		// DEVELOPMENT
		
		$this->description = "";
		$this->return_url = ROOT_DIR."vpos/returnvpos";			// LA DIRECCION RAIZ DE LA APLICACION
		$this->cancel_url = ROOT_DIR."vpos/cancel";			// DEFINIR ANTES DE LLAMAR A LA LIBRERIA
    }

	public function single_buy($data){
		$VpMonto = $data['amount'];
		$UsrCod = $data['UsrCod'];
		
		$shop_process_id = $this->insert($UsrCod,$VpMonto);
		
		$amount = $data['amount'].".00";
		$currency = "PYG";
		$this->token = md5($this->privateKey . $shop_process_id . $amount . $currency);
		
		$request = array(
			"public_key" => $this -> publicKey,
			"operation" => array(
				"token" => $this->token,
				"shop_process_id" => $shop_process_id,
				"amount" => $amount,
				"currency" => $currency,
				"additional_data" => "",
				"description" => $this->description,
				"return_url" => $this -> return_url,
				"cancel_url" => $this -> cancel_url
			),
			"test_client" => true
		);
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $this -> url . "/vpos/api/0.3/single_buy",
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $request
		));
		
		$resp = curl_exec($curl);
		curl_close($curl);
		
		if(is_string($resp)) $resp = json_decode($resp);
		
		if($resp->status == "success"){
			$shop_process_id = $this->setProcessID($shop_process_id,$resp->process_id);
			echo "<script> location.href = '".$this->url."/payment/single_buy?process_id=".$resp->process_id."' </script>";
		}else{
			
		}
		
		// EXAMPLE:  https://vpos.infonet.com.py/payment/single_buy?process_id=1veY2NMX3Od751Lu0sK5 
	}
	
	public function getConfirmation($shop_process_id){
		
		// $shop_process_id = $data['shop_process_id'];
		
		$this->token = md5($this->privateKey . $shop_process_id . "get_confirmation");
		// $this->token = md5($this->privateKey . $shop_process_id . "rollback" . "0.00");
		// echo $this->privateKey . $shop_process_id . "rollback" . "0.00";
		
		$request = array(
			"public_key" => $this -> publicKey,
			"operation" => array(
				"token" => $this->token,
				"shop_process_id" => $shop_process_id
			),
			"test_client" => true
		);
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $this -> url . "/vpos/api/0.3/single_buy/confirmations",
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => json_encode($request)
		));
		
		$resp = curl_exec($curl);
		curl_close($curl);
		
		if(is_string($resp)) $resp = json_decode($resp,true);
		
		// var_dump($resp);
		
		if($resp['confirmation']['response_code'] == "00"){
			$resp['confirmation']['VpStatus'] = "aprobado";
		}else{
			$resp['confirmation']['VpStatus'] = "rechazado";
		}
		
		$this->bancard_model->confirm($resp['confirmation']);
		
		return $resp['confirmation'];
	}
	
	public function rollback($shop_process_id){
				
		$this->token = md5($this->privateKey . $shop_process_id . "rollback" . "0.00");
		
		$request = array(
			"public_key" => $this -> publicKey,
			"operation" => array(
				"token" => $this->token,
				"shop_process_id" => $shop_process_id
			),
			"test_client" => true
		);
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $this -> url . "/vpos/api/0.3/single_buy/rollback",
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $request,
			CURLOPT_HTTPHEADER => 'Content-Type: application/json'
		));
		
		$resp = curl_exec($curl);
		
		if(is_string($resp)) $resp = json_decode($resp,true);
		
		curl_close($curl);
		
		$resp['shop_process_id'] = $shop_process_id;
		$resp['token'] = $this->token;
		
		$shop_process_id = $this->saveRollback($resp);
		
	}
	
	
	private function insert($UsrCod,$VpMonto){
		$sql = "INSERT INTO vpos (UsrCod,VpMonto,VpDate) VALUES ('{$UsrCod}','{$VpMonto}',NOW())";
		$result = $this->db->execute($sql);
		
		$sql = "SELECT * FROM vpos WHERE UsrCod = '{$UsrCod}' ORDER BY VpDate DESC LIMIT 1";
		$result = $this->db->getObject($sql);
		return $result->row()->VpCod;
	}
	
	private function setProcessID($shop_process_id,$process_id){
		$sql = "UPDATE vpos SET process_id = '{$process_id}' WHERE VpCod = '{$shop_process_id}'";
		$result = $this->db->execute($sql);
		return $result;
	}
	
	public function confirm($data){
		$sql = "UPDATE vpos SET 
					token 							= '{$data['token']}',
					response 						= '{$data['response']}',
					response_details 				= '{$data['response_details']}',
					response_code 					= '{$data['response_code']}',
					response_description 			= '{$data['response_description']}',
					extended_response_description 	= '{$data['extended_response_description']}',
					amount 							= '{$data['amount']}',
					currency 						= '{$data['currency']}',
					authorization_number 			= '{$data['authorization_number']}',
					ticket_number 					= '{$data['ticket_number']}',
					card_source 					= '{$data['security_information']['card_source']}',
					customer_ip 					= '{$data['security_information']['customer_ip']}',
					card_country 					= '{$data['security_information']['card_country']}',
					version 						= '{$data['security_information']['version']}',
					risk_index 						= '{$data['security_information']['risk_index']}'
				WHERE VpCod = '{$data['shop_process_id']}'";
				
		// echo "\n{$sql}\n";
		$result = $this->db->execute($sql);
		return $result;
	}

	public function lastBuy($UsrCod){
		$sql = "SELECT * FROM vpos WHERE UsrCod = '{$UsrCod}' ORDER BY VpDate DESC LIMIT 1";
		$result = $this->db->getObject($sql);
		return $result->row();
	}

	private function saveRollback($data){
		$sql = "UPDATE vpos SET 
					token 							= '{$data['token']}',
					RbStatus 						= '{$data['status']}',
					RbKey 							= '{$data['messages']['key']}',
					RbLevel 						= '{$data['messages']['level']}',
					RbDescription 					= '{$data['messages']['dsc']}'
				WHERE VpCod = '{$data['shop_process_id']}'";
		$result = $this->db->execute($sql);
		return $result;
	}
	
	private function createTable($data){
		
		$sql = "SHOW TABLES LIKE vpos";
		
		$result = $this->db->countRows($sql);
		
		if($result > 0){
			
			$sql = "CREATE TABLE `vpos` (
					 `VpCod` int(8) NOT NULL AUTO_INCREMENT,
					 `UsrCod` int(8) NOT NULL,
					 `VpMonto` bigint(25) NOT NULL,
					 `VpDate` datetime DEFAULT NULL,
					 `VpStatus` enum('pendiente','aprobado','rechazado','cancelado') NOT NULL DEFAULT 'pendiente',
					 `VpOrigin` varchar(255) NOT NULL,
					 `process_id` varchar(255) DEFAULT NULL,
					 `token` varchar(256) DEFAULT NULL,
					 `response` varchar(256) DEFAULT NULL,
					 `response_details` varchar(256) DEFAULT NULL,
					 `extended_response_description` varchar(256) DEFAULT NULL,
					 `currency` varchar(256) DEFAULT NULL,
					 `amount` varchar(256) DEFAULT NULL,
					 `authorization_number` varchar(256) DEFAULT NULL,
					 `ticket_number` varchar(256) DEFAULT NULL,
					 `response_code` varchar(256) DEFAULT NULL,
					 `response_description` varchar(256) DEFAULT NULL,
					 `customer_ip` varchar(256) DEFAULT NULL,
					 `card_source` varchar(256) DEFAULT NULL,
					 `card_country` varchar(256) DEFAULT NULL,
					 `version` varchar(256) DEFAULT NULL,
					 `risk_index` int(8) DEFAULT NULL,
					 `RbStatus` varchar(255) DEFAULT NULL,
					 `RbKey` varchar(255) DEFAULT NULL,
					 `RbLevel` varchar(255) DEFAULT NULL,
					 `RbDescription` varchar(255) DEFAULT NULL,
					 PRIMARY KEY (`VpCod`)
					) ENGINE=InnoDB DEFAULT CHARSET=latin1";

			$result = $this->db->execute($sql);
		}

	}
 }

?>
