<?php
require_once '../modelo/conexion.class.php';

$con = new DBManager();
$con->conectar();
$cadenaRetorno ="<table border='1'>";
if(is_array($_FILES)) {
	if(is_uploaded_file($_FILES['File']['tmp_name'])) {
		//$fecha = $_GET['fecha'];
		
		$filename = $_FILES['File']['tmp_name'];
		$handle = fopen($filename, "r");
		
		$cadenaRetorno .="<tr>";
		$cadenaRetorno .="<td>Fecha</td>";
		$cadenaRetorno .="<td>Hora</td>";
		$cadenaRetorno .="<td>Activo</td>";
		$cadenaRetorno .="<td>Operacion</td>";
		$cadenaRetorno .="<td>Monto</td>";
		$cadenaRetorno .="<td>Cantidad</td>";
		$cadenaRetorno .="<td> </td>";
		$cadenaRetorno .="<td> </td>";
		$cadenaRetorno .="<td> </td>";
		$cadenaRetorno .="<td>Cliente</td>";
		$cadenaRetorno .="<td>Error</td>";
		$cadenaRetorno .="</tr>";
		
		while (($data = fgetcsv($handle, 1000, ",")) !== FALSE)
		{
			//Insertamos los datos con los valores...
			$mensaje = "ok";
			
			$cadenaRetorno .="<tr>";
			$fechaoperacion = $data[0];
			$horaoperacion = $data[1];
			$activo = $data[2];
			$operacion = $data[3];
			$precio = $data[4];
			$cantidad = $data[5];
			$usuario = $data[9];
			$cadenaRetorno .="<td>". $data[0]."</td>";
			$cadenaRetorno .="<td>". $data[1]."</td>";
			$cadenaRetorno .="<td>". $data[2]."</td>";
			$cadenaRetorno .="<td>". $data[3]."</td>";
			$cadenaRetorno .="<td>". $data[4]."</td>";
			$cadenaRetorno .="<td>". $data[5]."</td>";
			$cadenaRetorno .="<td>". $data[6]."</td>";
			$cadenaRetorno .="<td>". $data[7]."</td>";
			$cadenaRetorno .="<td>". $data[8]."</td>";
			$cadenaRetorno .="<td>". $data[9]."</td>";

			//Obtenermos el activo

			$query="SELECT idActivo
                FROM activo
                WHERE estado=1 and codigo='".$activo."'";
			
			$arreglo = mysql_query($query);
			$var = mysql_fetch_array ( $arreglo  ) ;
			$idactivo = $var['idActivo'];
			
			
			//obtenemos el cliente
			$query="SELECT idUsuarios
                FROM usuarios
                WHERE estado=1 and codigo='".$usuario."'";
				
			$arreglo = mysql_query($query);
			$var = mysql_fetch_array ( $arreglo  ) ;
			$idusuario = $var['idUsuarios'];
			
			//comisiones
			$query="SELECT total,minimo
                FROM usuarios_valores
                WHERE estado=1 and idUsuarios='".$idusuario."'";
			
			$arreglo = mysql_query($query);
			$var = mysql_fetch_array ( $arreglo  ) ;
			$totalcomision = $var['total'];
			$minimocompra =  $var['minimo'];
			 
			//estado de cuenta
			$query="SELECT saldoActual
                FROM estadocuenta
                WHERE idCliente='".$idusuario."'";
				
			$arreglo = mysql_query($query);
			$var = mysql_fetch_array ( $arreglo  ) ;
			$SaldoActual = $var['saldoActual'];
			
			//portafolio
			$query="SELECT cantidad
	        FROM portafolios
	        WHERE estado=1 and idCliente='".$idusuario."' and idActivo='".$idactivo."'";
			
			$arreglo = mysql_query($query);
			$var = mysql_fetch_array ( $arreglo  ) ;
			$cantidadportafolio = $var['cantidad'];
			
			
			
			$anio =  substr($fechaoperacion, -4)."-".substr($fechaoperacion,3, -5)."-".substr($fechaoperacion,0, -8)."\n";
			$fechafinal = $anio." ".$horaoperacion;
			//b= compra, s = venta
			if($operacion=="B"){

				if($idactivo == ""){
					$mensaje = "El activo no es valido";
				}elseif($idusuario==""){
					$mensaje = "El usuario no es valido";
				}elseif($SaldoActual<$preciofinal){
					$mensaje = "Saldo insuficiente";
				}else{
					//Registrar operacion
					if($preciofinal<$minimocompra){
						$preciofinal = $minimocompra;
					}
					
					$preciofinal =	($precio*$cantidad)+($totalcomision*$cantidad);
					//echo $preciofinal." ".$precio." ".$totalcomision." ".$totalcomision." ".$cantidad;
					$saldofinal =$SaldoActual-$preciofinal;
					$hoy = date("Y-m-d");
					$sql = "INSERT into operacionesacciones(fechaHora,idActivo,idCliente,tipoOperacion,cantidad,precio,saldoFinal,estado,fechaCarga)";
					$sql .= "values('$fechafinal','$idactivo','$idusuario','$operacion','$cantidad','$preciofinal','$saldofinal',1,'$hoy')";
					mysql_query($sql);
					
					$sql = "UPDATE estadocuenta set saldoActual='".$saldofinal."' where idCliente='".$idusuario."'";
					mysql_query($sql);
					
					
					if($cantidadportafolio==""){
						$sql = "INSERT into portafolios(idActivo,idCliente,cantidad,estado)";
						$sql .= "values('$idactivo','$idusuario','$cantidad',1)";
						mysql_query($sql);
					}else{
						$cantidadActual = $cantidadportafolio+$cantidad;
						//echo "K ".$cantidadportafolio ." ".$cantidad." ".$cantidadActual;
						$sql = "UPDATE portafolios set cantidad ='".$cantidadActual."' where idCliente ='".$idusuario."' and idActivo='".$idactivo."'";
						mysql_query($sql);
					}
					
					
				}
				
				
			}elseif($operacion=="S"){
				if($idactivo == ""){
					$mensaje = "El activo no es valido";
				}elseif($idusuario==""){
					$mensaje = "El usuario no es valido";
				}elseif($cantidadportafolio==""){
						$mensaje = "No existe en el portafolio";
				}else{

					$preciofinal =	($precio*cantidad)-($totalcomision*cantidad);
					
					if($preciofinal<$minimocompra){
						$preciofinal = $minimocompra;
					}
					if($cantidadportafolio>$cantidad){
						

						$saldofinal =$SaldoActual+$preciofinal;
						$sql = "UPDATE estadocuenta set saldoActual='".$saldofinal."' where idCliente='".$idusuario."'";
						mysql_query($sql);
						$hoy = date("Y-m-d");
						$sql = "INSERT into operacionesacciones(fechaHora,idActivo,idCliente,tipoOperacion,cantidad,precio,saldoFinal,estado,fechaCarga)";
						$sql .= "values('$fechafinal','$idactivo','$idusuario','$operacion','$cantidad','$preciofinal','$saldofinal',1,'$hoy')";
						mysql_query($sql) ;
						
						$cantidadActual = $cantidadportafolio-$cantidad;
						$sql = "UPDATE portafolios set cantidad ='".$cantidadActual."' where idCliente ='".$idusuario."' and idActivo='".$idactivo."'";
						mysql_query($sql);
						
						
					}else{
						$mensaje = "Insuficiente activos en el portafolio";
					}
				}
			}else{
				$mensaje = "El tipo de operacion es invalida";
			}
			$cadenaRetorno .="<td>". $mensaje."</td>";
			$cadenaRetorno .="</tr>";
		}
		//cerramos la lectura del archivo "abrir archivo" con un "cerrar archivo"
		fclose($handle);
		$cadenaRetorno .="</table>";
		/*
		
		$sourcePath = $_FILES['File']['tmp_name'];
		$targetPath = "../FicherosUpload/".$fecha.$_FILES['File']['name'];
		if(move_uploaded_file($sourcePath,$targetPath)){
			echo $targetPath;
		}
		else
		{
			echo "error";
		}*/
	}
	else
	{
		$mensaje =  "error";
	}
}
else
{
	$mensaje = "error";
}
echo  $cadenaRetorno;
?>