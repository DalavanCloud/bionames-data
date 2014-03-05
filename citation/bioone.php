<?php

require('simplehtmldom_1_5/simple_html_dom.php');

require('../publications/reference.php');


//--------------------------------------------------------------------------------------------------
function authors_from_string($authorstring)
{
	$authors = array();
	
	// Strip out suffix
	$authorstring = preg_replace("/,\s*Jr./u", "", trim($authorstring));
	$authorstring = preg_replace("/,\s*jr./u", "", trim($authorstring));
	
	$authorstring = preg_replace("/,$/u", "", trim($authorstring));
	$authorstring = preg_replace("/&/u", "|", $authorstring);
	$authorstring = preg_replace("/;/u", "|", $authorstring);
	$authorstring = preg_replace("/ and /u", "|", $authorstring);
	$authorstring = preg_replace("/\.,/Uu", "|", $authorstring);				
	$authorstring = preg_replace("/\|\s*\|/Uu", "|", $authorstring);				
	$authorstring = preg_replace("/\|\s*/Uu", "|", $authorstring);				
	$authors = explode("|", $authorstring);
	
	for ($i = 0; $i < count($authors); $i++)
	{
		$authors[$i] = preg_replace('/\.([A-Z])/u', ". $1", $authors[$i]);
		$authors[$i] = preg_replace('/^\s+/u', "", $authors[$i]);
		$authors[$i] = preg_replace('/\,$/u', "", $authors[$i]);
		$authors[$i] = preg_replace('/\.$/u', "", $authors[$i]);
		$authors[$i] = mb_convert_case($authors[$i], MB_CASE_TITLE, 'UTF-8');
	}

	return $authors;
}


$filename = 'bioone.html';
//$filename = 'Chaerephon ansorgei.html';
//$filename = 'mops.html';
$filename = 'frost.html';
$filename = 'Vampyressa.html';
$filename = 'Kerivoula.html';

$filename = 'examples//bats.html';

$filename = 'examples/homonymy.html';


$html_content = file_get_contents($filename);

$html = str_get_html($html_content);

// extract literature cited from BioOne web page

$cites = array();

foreach($html->find('div.refnumber') as $element)
{
   //echo $element->innertext . "\n";
   
   //print_r($element);
   
   //exit();
   
   $citation = new stdclass;
   $citation->id = $element->id;
   $citation->html = $element->outertext;
   $citation->type = 'generic';
       
	$value = $element->find('span.NLM_year', 0);
   	if ($value)
   	{
		$citation->year = $value->plaintext;
		
		$citation->year = preg_replace('/[a-z]$/', '', $citation->year);
		
		// authors
		// <td valign="top">Van Cakenberghe, V. and F. de Vree. <span class="NLM_year">
		if (preg_match('/<td valign="top">(?<authorstring>.*)<span class="NLM_year">/', $element->innertext, $m))
		{
			$authorstring = $m['authorstring'];
			
			$authors = authors_from_string($authorstring);
			
			$citation->author = array();
			
			foreach ($authors as $a)
			{
				$author = new stdclass;
				
				if (preg_match('/^(?<lastname>\w+(-\w+)?),\s*(?<firstname>.*)$/', $a, $mm))
				{
					$author->firstname = str_replace('.', '', $mm['firstname']);
					$author->lastname = $mm['lastname'];
					$author->name = $author->firstname . ' ' . $author->lastname;
				}
				else
				{
					if (preg_match('/^(?<firstname>[A-Z]\.\s+)+(?<lastname>\w+(-\w+)?)$/', $a, $mm))
					{
						$author->firstname = str_replace('.', '', $mm['firstname']);
						$author->lastname = $mm['lastname'];
						$author->name = $author->firstname . ' ' . $author->lastname;
					}				
				}
				if (!isset($author->name))
				{
					$author->name = $a;
				}
			
				$citation->author[] = $author;
			}
		}
		
   	}
   	
	$value = $element->find('span.NLM_article-title', 0);
   	if ($value)
   	{
   		$citation->type = 'article';
		$citation->title = $value->plaintext;
		$citation->title = preg_replace('/\.$/', '', $citation->title);
   	}
   	
	$value = $element->find('span.citation_source-journal', 0);
   	if ($value)
   	{
   		$citation->type = 'article';
   		$citation->journal = new stdclass;
		$citation->journal->name = $value->plaintext;
   	}  
   	else
   	{
   		// If we have an article but no 'citation_source-journal' tag then we need to do some extra work
   		if ($citation->type == 'article')
   		{
   			if (preg_match('/<span class="NLM_article-title">(.*)<\/span>\s*(?<journal>.*)\s+(?<volume>\d+):<span class="NLM_fpage">/', $element->innertext, $m))
   			{
   				//print_r($m);
   				$citation->journal = new stdclass;
				$citation->journal->name = $m['journal'];
   				$citation->journal->volume = $m['volume'];
   			}
   		
   		}
   	
   	
   	}
   	
	$value = $element->find('span.citation_source-book', 0);
   	if ($value)
   	{
   		$citation->type = 'book';
   		$citation->title = $value->plaintext;
   	}
   	
	$value = $element->find('span.NLM_publisher-name', 0);
   	if ($value)
   	{
   		$citation->type = 'book';
   		
		$citation->publisher = new stdclass;
		$citation->publisher->name = $value->plaintext;
   	}
   	
	$value = $element->find('span.NLM_publisher-loc', 0);
   	if ($value)
   	{
   		$citation->type = 'book';
   		
   		if (!isset($citation->publisher))
   		{
			$citation->publisher = new stdclass;
		}
		$citation->publisher->address = $value->plaintext;
   	}
   	
	$value = $element->find('span.NLM_fpage', 0);
   	if ($value)
   	{
   		if (isset($citation->journal))
   		{
   			$citation->journal->pages = $value->plaintext;
   			
   			// volume not marked up (sigh)
   			if (preg_match('/<\/span>\s+(?<volume>\d+):<span class="NLM_fpage">/', $element->innertext, $m))
   			{
   				$citation->journal->volume = $m['volume'];
   			}
   		}
   		else
   		{
   			$citation->pages = $value->plaintext;
   		}
   	}   	
   	
	$value = $element->find('span.NLM_lpage', 0);
   	if ($value)
   	{
   		if (isset($citation->journal))
   		{
   			$citation->journal->pages .= '--' . $value->plaintext;
   		}
   		else
   		{
   			$citation->pages .= '--' . $value->plaintext;
   		}
   	}
	
	/*
	
	echo $element->find('span.NLM_article-title', 0)->plaintext . "\n";
	echo $element->find('span.citation_source-journal', 0)->plaintext . "\n";
	echo $element->find('span.citation_source-book', 0)->plaintext . "\n";
	echo $element->find('span.NLM_publisher-name', 0)->plaintext . "\n";
	echo $element->find('span.NLM_publisher-loc', 0)->plaintext . "\n";
	echo $element->find('span.NLM_fpage', 0)->plaintext . "\n";
    echo $element->find('span.NLM_lpage', 0)->plaintext . "\n";
    */
    
    // link
    $link = $element->find('a', 0)->href;
    if ($link != '')
    {
    	$link = urldecode($link);
    	if (preg_match('/key=(?<doi>.*)$/', $link, $m))
    	{
    		$identifier = new stdclass;
    		$identifier->type = 'doi';
    		$identifier->id = $m['doi'];
    		
    		$citation->identifier[] = $identifier;
    	}
    }
    
    
    $value = $element->find('span.citation_source-book', 0);
   	if ($value)
   	{
   		$citation->type = 'book';
   		$citation->title = $value->plaintext;
   	}
   	
    // try and fix up things we've missed
    if ($citation->type == 'book')
    {
    	if (!isset($citation->title))
    	{
    		if (preg_match('/<span class="NLM_year">\d+<\/span>(?<title>.*)\s*<span/Uu', $citation->html, $m))
    		{
    			$citation->title = $m['title'];
    			$citation->title = preg_replace('/^\./', '', $citation->title);
    			$citation->title = preg_replace('/^\s+/', '', $citation->title);
    			$citation->title = preg_replace('/\.$/', '', $citation->title);
    		}
    	}
    }
   	
    
    //print_r($citation);
    
    $cites[$citation->id] = $citation;
}


if (1)
{
	// dump JSON
	
	//print_r($cites);
	
	echo json_encode($cites);
	
}

if (0)
{
	// dump HTML

echo '<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
	<title>Untitled</title>
</head>
<body>';


foreach ($cites as $c)
{
	echo $c->html;
	echo '<a href="http://bionames.org/api/openurl?' . reference_to_openurl($c) . '" target="_new">BioNames</a>';
	echo '&nbsp;';
	echo '<a href="http://BioStor.org/openurl?' . reference_to_openurl($c) . '" target="_new">BioStor</a>';
	
	if (isset($c->identifier))
	{
		echo ' ' . $c->identifier[0]->id;
	}
	echo '<hr />';
}

echo '</body>
</html>';

}


?>