<?php

// Extract references from BMC XML


require_once('../../lib.php');

//--------------------------------------------------------------------------------------------------
function lookup($reference)
{	
	global $config;
	
	$ch = curl_init(); 
	
	$url = 'http://bionames.org/bionames-api/api_lookup.php';
	
	curl_setopt ($ch, CURLOPT_URL, $url); 
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1); 

	// Set HTTP headers
	$headers = array();
	$headers[] = 'Content-type: application/json'; // we are sending JSON
	
	// Override Expect: 100-continue header (may cause problems with HTTP proxies
	// http://the-stickman.com/web-development/php-and-curl-disabling-100-continue-header/
	$headers[] = 'Expect:'; 
	curl_setopt ($ch, CURLOPT_HTTPHEADER, $headers);
	
	if ($config['proxy_name'] != '')
	{
		curl_setopt($ch, CURLOPT_PROXY, $config['proxy_name'] . ':' . $config['proxy_port']);
	}

	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reference));
	
	$response = curl_exec($ch);
	
	echo $response;
	
	$obj = json_decode($response);

	return $obj;	
}

$basedir = '.';

$filename = '1742-9994-4-4.xml';

$filename = $basedir . '/' . $filename;

//echo $filename;

$xml = file_get_contents($filename);

if ($xml != '')
{
	$xp = new XsltProcessor();
	$xsl = new DomDocument;
	$xsl->load('bmc2bibjson.xsl');
	$xp->importStylesheet($xsl);
	
	$dom = new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);

	$json = $xp->transformToXML($dom);
	
	$references = json_decode($json);
	
	//echo $json;

	//print_r($references);

	$citations = array();


	foreach ($references as $reference)
	{
		$reference = lookup($reference);
		
		$citations[] = $reference;
	}

	echo "\n\n=====================\n\n";
	echo json_encode($citations);	
	echo "\n\n=====================\n\n";
	
	
	
}


?>
