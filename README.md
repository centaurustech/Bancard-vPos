# Bancard-vPos
Librería PHP para conexión al servicio vPos de Bancard.
Con esta clase se reducen los tiempos de desarrollo de la pasarela de pago y se reduce el margen de error Esta clase fue desarrollada siguiendo las documentaciones proveídas por Bancard

##Requerimientos
- Acceso al panel vPos de Bancard
- Una instancia de SimplePDO

##Atributos
####publicKey
La clave pública del vPos.
####privateKey
La clave privada del vPos. Ambos datos pueden encontrarse en el Panel de Comercios de Bancard.
####token
Es el token de acceso para las solicitudes al servidor de Bancard. Es generado por los metodos de single_buy y rollback
####url
Es la URL del servidor de Bancard. En el archivo PHP ya estan las la URL de producción y desarrollo y pueden cambiarse comentando las lineas entre si.
