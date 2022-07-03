<?php
$config_output_xml_file = "stormshield_import_to_zabbix.xml";  //File name created at the end
$config_zabbix_host_template = "Stormshield";  //Template name (must exist) for Zabbix template
$config_zabbix_groupname1 = "CLIENTS";  //Group-1 (must exist) for Zabbix template
$config_zabbix_groupname2 = "Firewall_Stormshield";  //Group-2 (must exist) for Zabbix template
$config_centreon_hosts_activated  = "1";  //Search only activated Hosts : 1 (0 for disabled Hosts / empty for all Hosts)
$config_centreon_search_alias = "stormshield";  //Search Hosts who contain in alias

$sql_host = "centreon.blabla.com";  //Centreon Host/IP for SQL querry
$sql_user_read = "read_only_user";  //Centreon SQL user (read-only is better :-) )
$sql_pass_read = "xxxxx";  //Centreon SQL password
$nom_base = "centreon";  //Centreon database

// *** Function for Mysqli connection on Centreon SQL database ***
function connect_read($sql_host,$sql_user_read,$sql_pass_read,$nom_base){
	global $mysqli;
	$mysqli = new mysqli("$sql_host", "$sql_user_read", "$sql_pass_read", "$nom_base");
	if ($mysqli->connect_error) {
		die('Erreur de connexion (' . $mysqli->connect_error . ') '
				. $mysqli->connect_error);
	}
	$mysqli->set_charset("utf8");
}

// *** Begin of the variable to create the Zabbix template file ***
$data_for_xml_file = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<zabbix_export>
    <version>5.0</version>
    <date>2022-06-27T09:46:55Z</date>
    <groups>
        <group>
            <name>$config_zabbix_groupname1</name>
        </group>
        <group>
            <name>$config_zabbix_groupname2</name>
        </group>
    </groups>
    <hosts>";

// Connect to the Centreon database
connect_read($sql_host,$sql_user_read,$sql_pass_read,$nom_base);
// Get informations about Hosts (id,name,alias,ip/dns,snmp informations, port)
$query = "SELECT host_id,host_name,host_alias,host_address,host_snmp_community,host_snmp_version,host_macro_value FROM host,on_demand_macro_host WHERE host_id = host_host_id AND host_activate='$config_centreon_hosts_activated' AND host_alias LIKE '%$config_centreon_search_alias%' ORDER BY host_id DESC";
$result = $mysqli->query($query);
// If SQL result return a result
if ($result->num_rows > 0){
	while($row = $result->fetch_array(MYSQLI_ASSOC)){
		$host_name = $row["host_name"];
		$host_name = str_replace("/", "_", $host_name);
		$host_alias = $row["host_alias"];
		$host_address = $row["host_address"];
		$host_snmp_community = $row["host_snmp_community"];
		$host_snmp_version = $row["host_snmp_version"];
		$host_snmp_port = $row["host_macro_value"];

		// *** Increment the variable to create the Zabbix template file ***
		$data_for_xml_file .= "
			<host>
				<host>$host_name</host>
				<name>$host_name</name>
				<description>$host_alias</description>
				<templates>
					<template>
						<name>$config_zabbix_host_template</name>
					</template>
				</templates>
				<groups>
					<group>
						<name>$config_zabbix_groupname1</name>
					</group>
					<group>
						<name>$config_zabbix_groupname2</name>
					</group>
				</groups>
				<interfaces>
					<interface>
						<type>SNMP</type>";
						if (filter_var($host_address, FILTER_VALIDATE_IP)){
							$data_for_xml_file .="
							<ip>$host_address</ip>";
						}
						else{
							$data_for_xml_file .="
							<useip>NO</useip>
							<ip/>
							<dns>$host_address</dns>";
						}
						$data_for_xml_file .="
							<port>$host_snmp_port</port>
						<details>
							<community>$host_snmp_community</community>
						</details>
						<interface_ref>if1</interface_ref>
					</interface>
				</interfaces>
				<inventory_mode>DISABLED</inventory_mode>
			</host>";
	}

// *** End of the variable to create the Zabbix template file ***
$data_for_xml_file .= "
		</hosts>
	</zabbix_export>";
	
file_put_contents($config_output_xml_file, $data_for_xml_file);
}
else
	echo "Error no Host(s) returned from Centreon database OR connection error to the database";

?>
