# Bancard-vPos
Librería PHP para conexión al servicio vPos de Bancard.
Con esta clase se reducen los tiempos de desarrollo de la pasarela de pago y se reduce el margen de error Esta clase fue desarrollada siguiendo las documentaciones proveídas por Bancard

##Requerimientos	
####  Conexión a Base de Datos
	Necesario para guardar las transacciones en la aplicación.
	Por defecto esta clase utiliza SimplePDO, una librería de conexion y manipulación de datos en MySQL o PgSQL por medio de PDO.
		
	Para bajar SimplePDO: https://github.com/rodolrojas/SimplePDO
	
	Pueden utilizarse otras clases de conexión (mysql_connect, mysqli, PDO crudo, Active Record, etc.),
	pero deberán cambiarse todas las funciones que impliquen manipulación de datos de acuerdo a la librería escogida. 

####	Claves de la API de Bancard
	Estas claves se obtienen del Portal de Comercios de Bancard

####	URLs de la plataforma de pagos
	Son propios de Bancard. No deben alterarse
	
####	URLs de retorno de la Aplicación
	Son los controladores a los que se accede una vez que se finaliza o cancela la transaccion.
#####	NO DEBEN CONFUNDIRSE CON LA URL DE CONFIRMACIÓN!!!


##Atributos
####publicKey
La clave pública del vPos.
####privateKey
La clave privada del vPos. Ambos datos pueden encontrarse en el Panel de Comercios de Bancard.
####token
Es el token de acceso para las solicitudes al servidor de Bancard. Es generado por los metodos de single_buy y rollback
####url
Es la URL del servidor de Bancard. En el archivo PHP ya estan las URLs de producción y desarrollo y pueden cambiarse comentando y descomentando las lineas entre si.

	
##Métodos		
		
####SINGLE BUY - single_buy($userID,$amount,$buyID = false): 
Genera una transacción interna y solicita un proceso de pago a la API de Bancard.

#####Parámetros recibidos:

######$userID
Es el identificador del cliente que realiza la compra, relacionado con la tabla de clientes de la aplicación.

######$amount
Es el monto a pagar de la transacción. Por limitaciones del servicio de vPos siempre debe estar en GUARANÍES (PYG).
Si las operaciones en la aplicación son en Dólares deberá hacerse una previa conversión al cambio del día o a una
cotización interna.

######$buyID
Si la aplicación ya tiene un registro propio de compras se puede utilizar su código de referencia como ID. Opcional

####OBTENER CONFIRMACIÓN - getConfirmation($shop_process_id): 
Solicita la confirmación de una transacción a la API enviando el ID interno y lo guarda en la tabla de pagos.

#####Parámetros recibidos:

######$shop_process_id
Es el identificador de la transacción generada a través de un SINGLE BUY.
ATENCIÓN!!! El ID que debe enviarse es el de la tabla ´vpos´ generada por esta librería.

####ROLLBACK - rollback($shop_process_id): 
Solicita la cancelación de una compra a la API enviando el ID interno y guarda la respuesta en la tabla de pagos.

#####Parámetros recibidos:

######$shop_process_id
Es el identificador de la transacción generada a través de un SINGLE BUY.
ATENCIÓN!!! El ID que debe enviarse es el de la tabla ´vpos´ generada por esta librería.

####INSERTAR TRANSACCIÓN - insert($UsrCod,$VpMonto,$VpCod = false):
Inserta los datos de la compra en la tabla de transacciones creada previamente por la librería.

#####Parámetros recibidos:

######$UsrCod
Es el código identificador del cliente.

######$VpMonto
Es el monto a abonar.
######$VpCod
Si la aplicación ya generó un identificador de compra, el registro puede agregarse con este codigo como ID. Parámetro Opcional

#####FUNCIÓN PRIVADA: SOLO ES EJECUTADO POR LA CLASE.

####GUARDAR ID DE PROCESO - setProcessID($shop_process_id,$process_id):
Una vez que la clase solicita un proceso de pago a la API de Bancard, este lo guarda en la tabla de pagos.

#####Parámetros recibidos:

######$shop_process_id
Es el código identificador de la transacción.
######$process_id
Es el identificador del proceso de pago generado por Bancard.

#####FUNCIÓN PRIVADA: SOLO ES EJECUTADO POR LA CLASE.

####GUARDAR CONFIRMACIÓN - confirm($data):
Al confirmarse la transacción (cuando al cliente le aparece Transacción Aprobada en la plataforma de pago)
Bancard envia los datos del pago a la aplicación. Este método guarda dichos datos.

#####Parámetros recibidos:

######$data
Bancard devuelve un objeto en formato JSON al confirmarse el pago. Dicho objeto debe pasarse convertido en un array.

####ÚLTIMA TRANSACCIÓN - lastBuy($UsrCod):
Obtiene la última transacción de un cliente.

#####Parámetros recibidos:

######$UsrCod
Es el identificador del cliente.

####GUARDAR ROLLBACK - saveRollback($data):
Guarda los resultados de la solicitud de rollback de una transacción. Opera de la misma forma que GUARDAR CONFIRMACIÓN

#####Parámetros recibidos:

######$data
Bancard devuelve un objeto en formato JSON al ejecutar el rollback. Dicho objeto debe pasarse convertido en un array.

#####FUNCIÓN PRIVADA: SOLO ES EJECUTADO POR LA CLASE.

####CREAR TABLA - createTable():
Crea la tabla de registro de transacciones. Verifica si dicha tabla ya ha sido creada

#####Parámetros recibidos: ninguno.

#####FUNCIÓN PRIVADA: SOLO ES EJECUTADO POR LA CLASE.
