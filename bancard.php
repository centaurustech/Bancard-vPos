<?php
class Bancard {
	
	var $publicKey;
	var $privateKey;
	var $token;
	var $url;
	
    function __construct()
    {
		
	/*
		CONEXION A BASE DE DATOS
		Necesario para guardar las transacciones en la aplicación.
		Por defecto esta clase utiliza SimplePDO, una librería de conexion y manipulación de datos en MySQL o PgSQL por medio de PDO.
		
		Para bajar SimplePDO: https://github.com/rodolrojas/SimplePDO
	
		Pueden utilizarse otras clases de conexión (mysql_connect, mysqli, PDO crudo, Active Record, etc.),
		pero deberán cambiarse todas las funciones que impliquen manipulación de datos de acuerdo a la librería escogida. 
	*/
	
		global $DB;
		
		if(!$DB){
			exit("Se requiere una conexion a base de datos para continuar!");
		}else{
			$this->db = $DB;											
			$this->createTable();												
		}

	/*
		CLAVES DE API
		Estas claves se obtienen del Portal de Comercios de Bancard
	*/
		
		$this->publicKey = "";									// CLAVE PUBLICA DE BANCARD
		$this->privateKey = "";									// CLAVE PRIVADA DE BANCARD
	
	/*
		URLs DE LA PLATAFORMA DE PAGOS
		Son propios de Bancard. No deben alterarse
	*/
		
		// $this->url = "https://vpos.infonet.com.py";			// PRODUCTION
		$this->url = "https://vpos.infonet.com.py:8888";		// DEVELOPMENT
		
	/*
		URLs de retorno de la Aplicación
		Son los controladores a los que se accede una vez que se finaliza o cancela la transaccion.
		
		NO DEBEN CONFUNDIRSE CON LA URL DE CONFIRMACIÓN!!!
	*/
		
		$this->description = "Hello World";					// NOMBRE DEL COMERCIO
		$this->return_url = ROOT_DIR."return.php";			// ROOT_DIR: ES LA DIRECCION RAIZ DE LA APLICACION
		$this->cancel_url = ROOT_DIR."cancel.php";			// DEBE DEFINIRSE ANTES DE LLAMAR A LA LIBRERIA
		
		// POR EJEMPLO: "http://www.example.com/" (se recomienda definir siempre con una diagonal al final de la URL).
    }

	public function single_buy($userID,$amount,$buyID = false){
		
		/*
			SINGLE BUY: Genera una transacción interna y solicita un proceso de pago a la API de Bancard.
			
			Parámetros recibidos:
			
			$userID: Es el identificador del cliente que realiza la compra, relacionado con la tabla de clientes de la aplicación.
			
			$amount: Es el monto a pagar de la transacción. Por limitaciones del servicio de vPos siempre debe estar en GUARANÍES (PYG).
					 Si las operaciones en la aplicación son en Dólares deberá hacerse una previa conversión al cambio del día o a una
					 cotización interna.
			
			$buyID: Si la aplicación ya tiene un registro propio de compras se puede utilizar su código de referencia como ID. Opcional
		*/
		
		$VpMonto = $amount;
		$UsrCod = $userID;
				
		$shop_process_id = $this->insert($UsrCod,$VpMonto,$buyID);
		
		$amount = $amount.".00";
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
			CURLOPT_POSTFIELDS => json_encode($request)
		));
		
		$resp = curl_exec($curl);
		curl_close($curl);
		
		if(is_string($resp)) $resp = json_decode($resp);
		
		if($resp->status == "success"){
			$shop_process_id = $this->setProcessID($shop_process_id,$resp->process_id);
			echo "<script> location.href = '".$this->url."/payment/single_buy?process_id=".$resp->process_id."' </script>";
		}else{
			echo "Ocurrió un error al conectar a Bancard!<br/>";
			echo "<pre>\n";
				var_dump($request);
				var_dump($resp);
			echo "</pre>\n";
			exit;
		}
		
		// EXAMPLE:  https://vpos.infonet.com.py/payment/single_buy?process_id=1veY2NMX3Od751Lu0sK5 
	}
	
	public function getConfirmation($shop_process_id){
		
		/*
			OBTENER CONFIRMACIÓN: Solicita la confirmación de una transacción a la API enviando el ID interno y lo guarda en la tabla de pagos.
			
			Parámetros recibidos:
			
			$shop_process_id: Es el identificador de la transacción generada a través de un SINGLE BUY.
							  ATENCIÓN!!! El ID que debe enviarse es el de la tabla ´vpos´ generada por esta librería.
		*/
		
		$this->token = md5($this->privateKey . $shop_process_id . "get_confirmation");

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
		
		if($resp['confirmation']['response_code'] == "00"){
			$resp['confirmation']['VpStatus'] = "aprobado";
		}else{
			$resp['confirmation']['VpStatus'] = "rechazado";
		}
		
		$this->bancard_model->confirm($resp['confirmation']);
		
		return $resp['confirmation'];
	}
	
	public function rollback($shop_process_id){
		
		/*
			ROLLBACK: Solicita la cancelación de una compra a la API enviando el ID interno y guarda la respuesta en la tabla de pagos.
			
			Parámetros recibidos:
			
			$shop_process_id: Es el identificador de la transacción generada a través de un SINGLE BUY.
							  ATENCIÓN!!! El ID que debe enviarse es el de la tabla ´vpos´ generada por esta librería.
		*/
		
				
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
	
	
	private function insert($UsrCod,$VpMonto,$VpCod = false){
		
		/*
			INSERTAR TRANSACCIÓN: Inserta los datos de la compra en la tabla de transacciones creada previamente por la librería.
			
			Parámetros recibidos:
			
			$UsrCod: Es el código identificador del cliente.
			$VpMonto: Es el monto a abonar.
			$VpCod: Si la aplicación ya generó un identificador de compra, el registro puede agregarse con este codigo como ID. Parámetro Opcional
			
			FUNCIÓN PRIVADA: SOLO ES EJECUTADO POR LA CLASE.
		*/
		
		if($VpCod){
			$sql = "INSERT INTO vpos (VpCod,UsrCod,VpMonto,VpDate) VALUES ('{$VpCod}','{$UsrCod}','{$VpMonto}',NOW())";
			$result = $this->db->execute($sql);
			return $VpCod;
		}else{
			$sql = "INSERT INTO vpos (UsrCod,VpMonto,VpDate) VALUES ('{$UsrCod}','{$VpMonto}',NOW())";
			$result = $this->db->execute($sql);
			
			$sql = "SELECT * FROM vpos WHERE UsrCod = '{$UsrCod}' ORDER BY VpDate DESC LIMIT 1";
			$result = $this->db->getObject($sql);
			return $result->VpCod;			
		}
	}
	
	private function setProcessID($shop_process_id,$process_id){
		
		/*
			GUARDAR ID DE PROCESO: Una vez que la clase solicita un proceso de pago a la API de Bancard, este lo guarda en la tabla de pagos.
			
			Parámetros recibidos:
			
			$shop_process_id: Es el código identificador de la transacción.
			$process_id: Es el identificador del proceso de pago generado por Bancard.
			
			FUNCIÓN PRIVADA: SOLO ES EJECUTADO POR LA CLASE.
		*/
		
		$sql = "UPDATE vpos SET process_id = '{$process_id}' WHERE VpCod = '{$shop_process_id}'";
		$result = $this->db->execute($sql);
		return $result;
	}
	
	public function confirm($data){
		
		/*
			GUARDAR CONFIRMACIÓN: Al confirmarse la transacción (cuando al cliente le aparece Transacción Aprobada en la plataforma de pago)
			Bancard envia los datos del pago a la aplicación. Este método guarda dichos datos.
			
			Parámetros recibidos:
			
			$data: Bancard devuelve un objeto en formato JSON al confirmarse el pago. Dicho objeto debe pasarse convertido en un array.
		*/
		
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
		
		/*
			ÚLTIMA TRANSACCIÓN: Obtiene la última transacción de un cliente.
			Parámetros recibidos:
			
			$UsrCod: Es el identificador del cliente.
		*/
		
		$sql = "SELECT * FROM vpos WHERE UsrCod = '{$UsrCod}' ORDER BY VpDate DESC LIMIT 1";
		$result = $this->db->getObject($sql);
		return $result[0];
	}

	private function saveRollback($data){
		
		/*
			GUARDAR ROLLBACK: Guarda los resultados de la solicitud de rollback de una transacción. Opera de la misma forma que GUARDAR CONFIRMACIÓN
			
			Parámetros recibidos:
			
			$data: Bancard devuelve un objeto en formato JSON al ejecutar el rollback. Dicho objeto debe pasarse convertido en un array.
			
			FUNCIÓN PRIVADA: SOLO ES EJECUTADO POR LA CLASE.
		*/
		
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
	
	private function createTable(){
		
		/*
			CREAR TABLA: Crea la tabla de registro de transacciones. Verifica si dicha tabla ya ha sido creada
			
			Parámetros recibidos: ninguno.
			
			FUNCIÓN PRIVADA: SOLO ES EJECUTADO POR LA CLASE.
		*/
		
		$sql = "SELECT *
				FROM information_schema.tables
				WHERE table_schema = '{$this->db->getDBName()}'
				AND table_name = 'vpos'";
		
		$result = $this->db->countRows($sql);
		
		if($result == 0){
			
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
