<?php

require 'config.inc.php';
require ('wampserver.lib.php');

//[modif oto] - Update wampserver version from modif oto
if(!empty($wampserverVersionOto) && $wampConf['wampserverVersion'] !== $wampserverVersionOto) {
	$wampConf['wampserverVersion'] = $newWampConf['wampserverVersion'] = $wampserverVersionOto;
	wampIniSet($configurationFile, $newWampConf);
}

// ************************ 
//   gestion de la langue


// on recupere la langue courante
if (isset($wampConf['language']))
	$lang = $wampConf['language'];
else 
  $lang = $wampConf['defaultLanguage'];

// on inclus le fichier correspondant
if (is_file($langDir.$lang.'.lang'))
	require($langDir.$lang.'.lang');
else
	require($langDir.$wampConf['defaultLanguage'].'lang');

// on inclus les fichiers de langue de modules par defaut
if ($handle = opendir($langDir.$modulesDir))
{
	while (false !== ($file = readdir($handle)))
	{
		if ($file != "." && $file != ".." && preg_match('|_'.$wampConf['defaultLanguage'].'|',$file))
			include($langDir.$modulesDir.$file);
	}
	closedir($handle);
}

// on inclus les fichiers de langue de modules correspondant � la langue courante
if ($handle = opendir($langDir.$modulesDir))
{
	while (false !== ($file = readdir($handle)))
	{
		if ($file != "." && $file != ".." && preg_match('|_'.$lang.'|',$file))
			include($langDir.$modulesDir.$file);
	}
	closedir($handle);
}

// ************************
//[modif oto] - Before to require wampmanager.tpl ($templateFile)
// we need to change some options, otherwise the variables are replaced by their content.
// Option to launch Homepage at startup
$wampTplFileContents = @file_get_contents($templateFile) or die ("wampmanager.tpl file not found");
$wampStartup = ";WAMPSTARTUPACTIONEND";
$startup_line_on = "Action: run; FileName: \"\${c_navigator}\"; Parameters: \"http://localhost/\"; Flags: ignoreerrors";
$startup_on = $replace_tpl = false;
// Homepage on startup already exists?
if(strpos($wampTplFileContents, $startup_line_on) !== false)
	$startup_on = true;
// Option exists?
if(isset($wampConf['HomepageAtStartup'])) {
	if($startup_on && $wampConf['HomepageAtStartup'] == "off") {
		$wampTplFileContents = str_replace($startup_line_on."
".$wampStartup, $wampStartup, $wampTplFileContents);
	$replace_tpl = true;
	}
	if(!$startup_on && $wampConf['HomepageAtStartup'] == "on") {
		$wampTplFileContents = str_replace($wampStartup, $startup_line_on."
".$wampStartup, $wampTplFileContents);
	$replace_tpl = true;
	}
	if($replace_tpl)
		file_put_contents($templateFile, $wampTplFileContents);
}
// Item menu Online / Offline
$wampOnlineOffline = ";WAMPMENULEFTEND";
$online_line_on = "Type: item; Caption: \"\${w_putOnline}\"; Action: multi; Actions: onlineoffline";
$online_on = $replace_tpl = false;
// Put Online Menu item already exists?
if(strpos($wampTplFileContents, $online_line_on) !== false)
	$online_on = true;
// Option exists?
if(isset($wampConf['MenuItemOnline'])) {
	if($online_on && $wampConf['MenuItemOnline'] == "off") {
		$wampTplFileContents = str_replace($online_line_on."
".$wampOnlineOffline, $wampOnlineOffline, $wampTplFileContents);
	$replace_tpl = true;
	}
	if(!$online_on && $wampConf['MenuItemOnline'] == "on") {
		$wampTplFileContents = str_replace($wampOnlineOffline, $online_line_on."
".$wampOnlineOffline, $wampTplFileContents);
	$replace_tpl = true;
	}
	if($replace_tpl)
		file_put_contents($templateFile, $wampTplFileContents);
}
unset($wampTplFileContents);

// on inclus le fichier de template
require($templateFile);

// ************************
// on gere le mode online /offline
if ($wampConf['status'] == 'online')
{
	$tpl = str_replace('images_off.bmp', 'images_on.bmp',$tpl);
  $tpl = str_replace($w_serverOffline, $w_serverOnline,$tpl);
  $tpl = str_replace('onlineOffline.php on', 'onlineOffline.php off', $tpl);
  $tpl = str_replace($w_putOnline,$w_putOffline,$tpl);
}

// ************************
// chargement du menu des langues disponibles
if ($handle = opendir($langDir))
{
	while (false !== ($file = readdir($handle)))
	{
		if ($file != "." && $file != ".." && preg_match('|\.lang|',$file))
		{
			if ($file == $lang.'.lang')
				$langList[$file] = 1;
			else
				$langList[$file] = 0;
		}
	}
	closedir($handle);
}

$langText = ";WAMPLANGUAGESTART
";
ksort($langList);
foreach ($langList as $langname=>$langstatus)
{
  $cleanLangName = str_replace('.lang','',$langname);
  if ($langList[$langname] == 1)
    $langText .= 'Type: item; Caption: "'.$cleanLangName.'"; Glyph: 13; Action: multi; Actions: lang_'.$cleanLangName.'
';
  else
    $langText .= 'Type: item; Caption: "'.$cleanLangName.'"; Action: multi; Actions: lang_'.$cleanLangName.'
';

}

foreach ($langList as $langname=>$langstatus)
{
  $cleanLangName = str_replace('.lang','',$langname);
  $langText .= '[lang_'.$cleanLangName.']
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . changeLanguage.php '.$cleanLangName.'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . refresh.php";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: resetservices
Action: readconfig;

';
    
}

$tpl = str_replace(';WAMPLANGUAGESTART',$langText,$tpl);

// ************************
// chargement du menu d'extensions de PHP
$myphpini = @file($c_phpConfFile) or die ("php.ini file not found");

//on recupere la conf courante
foreach($myphpini as $line) {
  $extMatch = array();
  if(preg_match('/^(;)?extension\s*=\s*"?([a-z0-9_]+)\.dll"?/i', $line, $extMatch)) {
    $ext_name = $extMatch[2];
    if($extMatch[1] == ';') {
      $ext[$ext_name] = '0';
    } else {
      $ext[$ext_name] = '1';
    }
  }
}

// on recupere la liste d'extensions presentes dans le r�pertoire ext
if ($handle = opendir($phpExtDir)) 
{
  while (false !== ($file = readdir($handle))) 
  {
    if ($file != "." && $file != ".." && strstr($file,'.dll')) 
      $extDirContents[] = str_replace('.dll','',$file);
  }
  closedir($handle);
}

// on croise les deux tableaux
foreach ($extDirContents as $extname)
{
  if(in_array($extname, $phpNotLoadExt)) {
  	$ext[$extname] = -3; //[modif oto] - dll not to be loaded by extension = in php.ini
  	continue;
  }
  if (!array_key_exists($extname,$ext))
    $ext[$extname] = -1; //[modif oto] - dll file exists but not extension line in php.ini
}
foreach ($ext as $extname=>$value)
{
  if (!in_array($extname,$extDirContents))
    $ext[$extname] = -2; //[modif oto] - extension line in php.ini but not dll file
}

ksort($ext);

//on construit le menu correspondant
$extText = ';WAMPPHP_EXTSTART
';
foreach ($ext as $extname=>$extstatus)
{
  if ($ext[$extname] == 1)
    $extText .= 'Type: item; Caption: "'.$extname.'"; Glyph: 13; Action: multi; Actions: php_ext_'.$extname.'
';
  elseif($ext[$extname] == -1)
  {
   	//[modif oto] - Warning icon to indicate problem with this extension: No extension line in php.ini
    $extText .= 'Type: item; Caption: "'.$extname.'"; Action: multi; Actions: php_ext_'.$extname.' ; Glyph: 19;  
';
	}
  elseif($ext[$extname] == -2)
  {
   	//[modif oto] - Square red icon to indicate problem with this extension: no dll file in ext directory
    $extText .= 'Type: item; Caption: "'.$extname.'"; Action: multi; Actions: php_ext_'.$extname.' ; Glyph: 11;
';
	}
  elseif($ext[$extname] == -3)
  {
   	//[modif oto] - blue || icon to indicate that the dll must not be loaded by extension = in php.ini
    $extText .= 'Type: item; Caption: "'.$extname.'"; Action: multi; Actions: php_ext_'.$extname.' ; Glyph: 10;
';
	}
  else
  {
    $extText .= 'Type: item; Caption: "'.$extname.'"; Action: multi; Actions: php_ext_'.$extname.'
';
	}
}

foreach ($ext as $extname=>$extstatus)
{
  if ($ext[$extname] == 1)
    $extText .= '[php_ext_'.$extname.']
Action: service; Service: '.$c_apacheService.'; ServiceAction: stop; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . switchPhpExt.php '.$extname.' off";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . refresh.php";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "net"; Parameters: "start '.$c_apacheService.'"; ShowCmd: hidden; Flags: waituntilterminated
Action: resetservices;
Action: readconfig;
';
  elseif ($ext[$extname] == 0)
    $extText .= '[php_ext_'.$extname.']
Action: service; Service: '.$c_apacheService.'; ServiceAction: stop; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . switchPhpExt.php '.$extname.' on";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . refresh.php";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "net"; Parameters: "start '.$c_apacheService.'"; ShowCmd: hidden; Flags: waituntilterminated
Action: resetservices
Action: readconfig;
';
  elseif ($ext[$extname] == -1)
    $extText .= '[php_ext_'.$extname.']
Action: run; FileName: "'.$c_phpExe.'";Parameters: "msg.php 3 '.base64_encode($extname).'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
';
  elseif ($ext[$extname] == -2)
    $extText .= '[php_ext_'.$extname.']
Action: run; FileName: "'.$c_phpExe.'";Parameters: "msg.php 4 '.base64_encode($extname).'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
';
  elseif ($ext[$extname] == -3)
    $extText .= '[php_ext_'.$extname.']
Action: run; FileName: "'.$c_phpExe.'";Parameters: "msg.php 5 '.base64_encode($extname).'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
';
}

$tpl = str_replace(';WAMPPHP_EXTSTART',$extText,$tpl);

// ************************
// menu de configuration de PHP
$myphpini = parse_ini_file($c_phpConfFile);

// on recupere les valeurs dans le php.ini
foreach($phpParams as $next_param_name=>$next_param_text)
{
  if (isset($myphpini[$next_param_text]))
  {
  	switch($myphpini[$next_param_text]) {
  		case 'On':
  		case 1:
  			$params_for_wampini[$next_param_name] = '1';
  		break;
  		case 'Off':
  		case 0:
  			$params_for_wampini[$next_param_name] = '0';
  		break;
  		default:
  			$params_for_wampini[$next_param_name] = -2;
  		break;
  	}
  }
  else //[modif oto] - Parameter in $phpParams (config.inc.php) does not exist in php.ini
    $params_for_wampini[$next_param_name] = -1;
}

$phpConfText = ";WAMPPHP_PARAMSSTART
";
foreach ($params_for_wampini as $paramname=>$paramstatus)
{
  if ($params_for_wampini[$paramname] == 1)
    $phpConfText .= 'Type: item; Caption: "'.$paramname.'"; Glyph: 13; Action: multi; Actions: '.$phpParams[$paramname].'
';
  elseif ($params_for_wampini[$paramname] == 0) //[modif oto] - It does not display non-existent settings in php.ini
    $phpConfText .= 'Type: item; Caption: "'.$paramname.'"; Action: multi; Actions: '.$phpParams[$paramname].'
';
  elseif ($params_for_wampini[$paramname] == -2) //[modif oto] - || blue to indicate different from 0 or 1 or On or Off
     $phpConfText .= 'Type: item; Caption: "'.$paramname.'"; Action: multi; Actions: '.$phpParams[$paramname].' ; Glyph: 10;
';
}

foreach ($params_for_wampini as $paramname=>$paramstatus)
{
  if ($params_for_wampini[$paramname] == 1)
  $phpConfText .= '['.$phpParams[$paramname].']
Action: service; Service: '.$c_apacheService.'; ServiceAction: stop; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "switchPhpParam.php '.$phpParams[$paramname].' off";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . refresh.php";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "net"; Parameters: "start '.$c_apacheService.'"; ShowCmd: hidden; Flags: waituntilterminated
Action: resetservices
Action: readconfig;
';
  elseif ($params_for_wampini[$paramname] == 0)  //[modif oto] - It does not act for non-existent settings in php.ini
  	$phpConfText .= '['.$phpParams[$paramname].']
Action: service; Service: '.$c_apacheService.'; ServiceAction: stop; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "switchPhpParam.php '.$phpParams[$paramname].' on";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . refresh.php";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "net"; Parameters: "start '.$c_apacheService.'"; ShowCmd: hidden; Flags: waituntilterminated
Action: resetservices
Action: readconfig;
';
  elseif ($params_for_wampini[$paramname] == -2)  //[modif oto] - Parameter is neither 'on' nor 'off'
  	$phpConfText .= '['.$phpParams[$paramname].']
Action: run; FileName: "'.$c_phpExe.'";Parameters: "msg.php 6 '.base64_encode($paramname).'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
';
 
}

$tpl = str_replace(';WAMPPHP_PARAMSSTART',$phpConfText,$tpl);

// ************************
// modules Apache
$myhttpd = @file($c_apacheConfFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) or die ("httpd.conf file not found");

$recherche = array("modules/");
$mod_load = array();
foreach($myhttpd as $line)
{
  if (preg_match('|^#LoadModule|',$line))
  {
    $mod_table = explode(' ', $line);
    $mod_name = $mod_table[1];
    $mod[$mod_name] = '0';
		$load_module = str_replace($recherche,'',$mod_table[2]);
		$mod_load[$mod_name] = $load_module;
  }
  elseif (preg_match('|^LoadModule|',$line))
  {    
    $mod_table = explode(' ', $line);
    $mod_name = $mod_table[1];
    $mod[$mod_name] = '1';
		$load_module = str_replace($recherche,'',$mod_table[2]);
		$mod_load[$mod_name] = $load_module;
  }
}

// on recup�re la liste des modules pr�sents dans le r�pertoire /modules/
$modDirContents = array();
if ($handle = opendir($c_apacheConfFile = $c_apacheVersionDir.'/apache'.$wampConf['apacheVersion'].'/modules/')) 
{
  while (false !== ($file = readdir($handle))) 
  {
    if ($file != "." && $file != ".." && strstr($file,'.so')) 
			$modDirContents[] = $file;
  }
  closedir($handle);
}
//[modif oto] - On croise les tableaux
//D�tection pr�sence du module xxxxxx.so demand� par Loadmodule
foreach ($mod as $modname=>$value) 
{
	if(in_array($modname, $apacheModNotDisable)) {
		$mod[$modname] = -3 ; //not to be switched in Apache Modules sub-menu
		continue;
	}
	if(!in_array($mod_load[$modname], $modDirContents))
		$mod[$modname] = -1 ;
}
//D�tection de Loadmodule dans httpd.conf pour chaque module dans /modules/
foreach($modDirContents as $module)
{
	if(!in_array($module, $mod_load))
	{
		$modname = str_replace(array("mod_",".so"),array("","_module"),$module);
		$mod[$modname] = -2 ;
	}
}
ksort($mod);

$httpdText = ";WAMPAPACHE_MODSTART
";

foreach ($mod as $modname=>$modstatus)
{
  if ($modstatus == 1)
    $httpdText .= 'Type: item; Caption: "'.$modname.'"; Glyph: 13; Action: multi; Actions: apache_mod_'.$modname.'
';
	elseif ($modstatus == -1)
		$httpdText .= 'Type: item; Caption: "'.$modname.'"; Action: multi; Actions: apache_mod_'.$modname.' ; Glyph: 11;
';
	elseif ($modstatus == -2)
		$httpdText .= 'Type: item; Caption: "'.$modname.'"; Action: multi; Actions: apache_mod_'.$modname.' ; Glyph: 19;
';
	elseif ($modstatus == -3)
		$httpdText .= 'Type: item; Caption: "'.$modname.'"; Action: multi; Actions: apache_mod_'.$modname.' ; Glyph: 10;
';
  else
    $httpdText .= 'Type: item; Caption: "'.$modname.'"; Action: multi; Actions: apache_mod_'.$modname.'
';

}

foreach ($mod as $modname=>$modstatus)
{
  if ($mod[$modname] == 1)
    $httpdText .= '[apache_mod_'.$modname.']
Action: service; Service: '.$c_apacheService.'; ServiceAction: stop; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "switchApacheMod.php '.$modname.' on";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . refresh.php";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "net"; Parameters: "start '.$c_apacheService.'"; ShowCmd: hidden; Flags: waituntilterminated
Action: resetservices
Action: readconfig;
';
  elseif ($mod[$modname] == 0)
    $httpdText .= '[apache_mod_'.$modname.']
Action: service; Service: '.$c_apacheService.'; ServiceAction: stop; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "switchApacheMod.php '.$modname.' off";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . refresh.php";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "net"; Parameters: "start '.$c_apacheService.'"; ShowCmd: hidden; Flags: waituntilterminated
Action: resetservices
Action: readconfig;
';
  elseif ($mod[$modname] == -1)
    $httpdText .= '[apache_mod_'.$modname.']
Action: run; FileName: "'.$c_phpExe.'";Parameters: "msg.php 7 '.base64_encode($modname).'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
';
  elseif ($mod[$modname] == -2)
    $httpdText .= '[apache_mod_'.$modname.']
Action: run; FileName: "'.$c_phpExe.'";Parameters: "msg.php 8 '.base64_encode($modname).'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
';
  elseif ($mod[$modname] == -3)
    $httpdText .= '[apache_mod_'.$modname.']
Action: run; FileName: "'.$c_phpExe.'";Parameters: "msg.php 12 '.base64_encode($modname).'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
';
   
}

$tpl = str_replace(';WAMPAPACHE_MODSTART',$httpdText,$tpl);

// ************************
// alias Apache
if ($handle = opendir($aliasDir)) 
{
  while (false !== ($file = readdir($handle))) 
  {
    if ($file != "." && $file != ".." && strstr($file,'.conf')) 
      $aliasDirContents[] = $file;
  }
  closedir($handle);
}

$myreplace = $myreplacemenu = $mydeletemenu = '';
foreach ($aliasDirContents as $one_alias)
{
  $mypattern = ';WAMPADDALIAS';
  $newalias_dir = str_replace('.conf','',$one_alias);
  $alias_contents = @file_get_contents ($aliasDir.$one_alias);
  preg_match('|^Alias /'.$newalias_dir.'/ "(.+)"|',$alias_contents,$match);
  if (isset($match[1]))
    $newalias_dest = $match[1]; 
  else
    $newalias_dest = NULL;
  
    $myreplace .= 'Type: submenu; Caption: "http://localhost/'.$newalias_dir.'/"; SubMenu: alias_'.str_replace(' ','_',$newalias_dir).'; Glyph: 3
';

  $myreplacemenu .= '
[alias_'.str_replace(' ','_',$newalias_dir).']
Type: separator; Caption: "'.$newalias_dir.'"
Type: item; Caption: "Edit alias"; Glyph: 6; Action: multi; Actions: edit_'.str_replace(' ','_',$newalias_dir).'
Type: item; Caption: "Edit .htaccess"; Glyph: 6; Action: run; FileName: "'.$c_editor.'"; parameters: "'.$newalias_dest.'.htaccess"
Type: item; Caption: "Delete alias"; Glyph: 6; Action: multi; Actions: delete_'.str_replace(' ','_',$newalias_dir).'
';

  $mydeletemenu .= '
[delete_'.str_replace(' ','_',$newalias_dir).']
Action: service; Service: '.$c_apacheService.'; ServiceAction: stop; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpExe.'";Parameters: "-c . deleteAlias.php '.str_replace(' ','-whitespace-',$newalias_dir).'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "refresh.php";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "net"; Parameters: "start '.$c_apacheService.'"; ShowCmd: hidden; Flags: waituntilterminated
Action: resetservices
Action: readconfig;
[edit_'.str_replace(' ','_',$newalias_dir).']
Action: run; FileName: "'.$c_editor.'"; parameters:"'.$c_installDir.'/alias/'.$newalias_dir.'.conf"; Flags: waituntilterminated
Action: service; Service: '.$c_apacheService.'; ServiceAction: restart;
';

}

$tpl = str_replace($mypattern,$myreplace.$myreplacemenu.$mydeletemenu,$tpl);

// ************************
// versions de PHP
$phpVersionList = listDir($c_phpVersionDir,'checkPhpConf');

$myPattern = ';WAMPPHPVERSIONSTART';
$myreplace = $myPattern."
";
$myreplacemenu = '';    
foreach ($phpVersionList as $onePhp)
{
  $phpGlyph = '';
  $onePhpVersion = str_ireplace('php','',$onePhp);
  //on verifie si le PHP est compatible avec la version d'apache courante
  unset($phpConf);
  include $c_phpVersionDir.'/php'.$onePhpVersion.'/'.$wampBinConfFiles;
  
  $apacheVersionTemp = $wampConf['apacheVersion'];
  while (!isset($phpConf['apache'][$apacheVersionTemp]) && $apacheVersionTemp != '')
  {
    $pos = strrpos($apacheVersionTemp,'.');
    $apacheVersionTemp = substr($apacheVersionTemp,0,$pos);
  }

  // PHP incompatible avec la version courante d'apache
  $incompatiblePhp = 0;
  if (empty($apacheVersionTemp))
  {
    $incompatiblePhp = -1;
    $phpGlyph = '; Glyph: 19';
		$phpErrorMsg = "apacheVersion = empty in wampmanager.conf file";
  }
  elseif (empty($phpConf['apache'][$apacheVersionTemp]['LoadModuleFile']))
  {
    $incompatiblePhp = -2;
    $phpGlyph = '; Glyph: 19';
		$phpErrorMsg = "\$phpConf['apache']['".$apacheVersionTemp."']['LoadModuleFile'] does not exists or is empty in ".$c_phpVersionDir.'/php'.$onePhpVersion.'/'.$wampBinConfFiles;
  }
  elseif (!file_exists($c_phpVersionDir.'/php'.$onePhpVersion.'/'.$phpConf['apache'][$apacheVersionTemp]['LoadModuleFile']))
  {
    $incompatiblePhp = -3;
    $phpGlyph = '; Glyph: 19';
		$phpErrorMsg = $c_phpVersionDir.'/php'.$onePhpVersion.'/'.$phpConf['apache'][$apacheVersionTemp]['LoadModuleFile']." does not exists.";
  }
    
  if ($onePhpVersion === $wampConf['phpVersion'])
    $phpGlyph = '; Glyph: 13';
    
    $myreplace .= 'Type: item; Caption: "'.$onePhpVersion.'"; Action: multi; Actions:switchPhp'.$onePhpVersion.$phpGlyph.'
';
  if ($incompatiblePhp == 0)
  {
  $myreplacemenu .= '[switchPhp'.$onePhpVersion.']
Action: service; Service: '.$c_apacheService.'; ServiceAction: stop; Flags: ignoreerrors waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "switchPhpVersion.php '.$onePhpVersion.'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . refresh.php";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "net"; Parameters: "start '.$c_apacheService.'"; ShowCmd: hidden; Flags: waituntilterminated
Action: resetservices
Action: readconfig;
';
  }
  else
  {
  $myreplacemenu .= '[switchPhp'.$onePhpVersion.']
Action: run; FileName: "'.$c_phpExe.'";Parameters: "msg.php 1 '.base64_encode($onePhpVersion).' '.base64_encode($phpErrorMsg).'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
';
  }
    
}
/*$myreplace .= 'Type: separator;
Type: item; Caption: "Get more..."; Action: run; FileName: "'.$c_navigator.'"; Parameters: "http://www.wampserver.com/addons_php.php";
';*/

$tpl = str_replace($myPattern,$myreplace.$myreplacemenu,$tpl);

// ************************
// versions de Apache

$apacheVersionList = listDir($c_apacheVersionDir,'checkApacheConf');

$myPattern = ';WAMPAPACHEVERSIONSTART';
$myreplace = $myPattern."
";
$myreplacemenu = '';    

foreach ($apacheVersionList as $oneApache)
{
  $apacheGlyph = '';
  $oneApacheVersion = str_ireplace('apache','',$oneApache);

	//on verifie si le apache est compatible avec la version d'apache courante
  unset($phpConf);
  include $c_phpVersionDir.'/php'.$wampConf['phpVersion'].'/'.$wampBinConfFiles;
  $apacheVersionTemp = $oneApacheVersion;
  while (!isset($phpConf['apache'][$apacheVersionTemp]) && $apacheVersionTemp != '')
  {
    $pos = strrpos($apacheVersionTemp,'.');
    $apacheVersionTemp = substr($apacheVersionTemp,0,$pos);
  }
     
  // apache incompatible avec la version courante de PHP [modif oto-25]
  $incompatibleApache = 0;
  if (empty($apacheVersionTemp))
  {
    $incompatibleApache = -1;
    $apacheGlyph = '; Glyph: 19';
		$apacheErrorMsg = "apacheVersion = empty in wampmanager.conf file";
  }
  elseif (!isset($phpConf['apache'][$apacheVersionTemp]['LoadModuleFile'])
      || empty($phpConf['apache'][$apacheVersionTemp]['LoadModuleFile']))
  {
    $incompatibleApache = -2;
    $apacheGlyph = '; Glyph: 19';
		$apacheErrorMsg = "\$phpConf['apache']['".$apacheVersionTemp."']['LoadModuleFile'] does not exists or is empty in ".$c_phpVersionDir.'/php'.$wampConf['phpVersion'].'/'.$wampBinConfFiles;
  }
  elseif (!file_exists($c_phpVersionDir.'/php'.$wampConf['phpVersion'].'/'.$phpConf['apache'][$apacheVersionTemp]['LoadModuleFile']))
  {
    $incompatibleApache = -3;
    $apacheGlyph = '; Glyph: 19';
		$apacheErrorMsg = $c_phpVersionDir.'/php'.$wampConf['phpVersion'].'/'.$phpConf['apache'][$apacheVersionTemp]['LoadModuleFile']." does not exists.\n\nFirst switch on a version of PHP that contains ".$phpConf['apache'][$apacheVersionTemp]['LoadModuleFile']." file before you change to Apache version ".$oneApacheVersion.".";
  }

  if (isset($apacheConf))
    $apacheConf = NULL;
  include $c_apacheVersionDir.'/apache'.$oneApacheVersion.'/'.$wampBinConfFiles;
    
  if ($oneApacheVersion === $wampConf['apacheVersion'])
    $apacheGlyph = '; Glyph: 13';
    
  $myreplace .= 'Type: item; Caption: "'.$oneApacheVersion.'"; Action: multi; Actions:switchApache'.$oneApacheVersion.$apacheGlyph.'
';

  if ($incompatibleApache == 0)
  {
    $myreplacemenu .= '[switchApache'.$oneApacheVersion.']
Action: service; Service: '.$c_apacheService.'; ServiceAction: stop; Flags: ignoreerrors waituntilterminated
Action: run; FileName: "'.$c_apacheExe.'"; Parameters: "'.$c_apacheServiceRemoveParams.'"; ShowCmd: hidden; Flags: ignoreerrors waituntilterminated
Action: closeservices; Flags: ignoreerrors
Action: run; FileName: "'.$c_phpCli.'";Parameters: "switchApacheVersion.php '.$oneApacheVersion.'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "switchPhpVersion.php '.$wampConf['phpVersion'].'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "'.$c_apacheVersionDir.'/apache'.$oneApacheVersion.'/'.$apacheConf['apacheExeDir'].'/'.$apacheConf['apacheExeFile'].'"; Parameters: "'.$apacheConf['apacheServiceInstallParams'].'"; ShowCmd: hidden; Flags: waituntilterminated
Action: run; FileName: "reg"; Parameters: "add HKLM\SYSTEM\CurrentControlSet\Services\\'.$c_apacheService.' /V Start /t REG_DWORD /d 3 /f"; ShowCmd: hidden; Flags: waituntilterminated
Action: run; FileName: "net"; Parameters: "start '.$c_apacheService.'"; ShowCmd: hidden; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . refresh.php";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: resetservices
Action: readconfig;
';
  }
  else
  {
    $myreplacemenu .= '[switchApache'.$oneApacheVersion.']
Action: run; FileName: "'.$c_phpExe.'";Parameters: "msg.php 2 '.base64_encode($oneApacheVersion).' '.base64_encode($apacheErrorMsg).'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
';
  }
}
/*$myreplace .= 'Type: separator;
Type: item; Caption: "Get more..."; Action: run; FileName: "'.$c_navigator.'"; Parameters: "http://www.wampserver.com/addons_apache.php";
';*/

$tpl = str_replace($myPattern,$myreplace.$myreplacemenu,$tpl);

// ************************
// versions de MySQL
$mysqlVersionList = listDir($c_mysqlVersionDir,'checkMysqlConf');

$myPattern = ';WAMPMYSQLVERSIONSTART';
$myreplace = $myPattern."
";
$myreplacemenu = '';    
foreach ($mysqlVersionList as $oneMysql)
{
  $oneMysqlVersion = str_ireplace('mysql','',$oneMysql);
  unset($mysqlConf);
  include $c_mysqlVersionDir.'/mysql'.$oneMysqlVersion.'/'.$wampBinConfFiles;
  
	//[modif oto] - Check name of the group [wamp...] under '# The MySQL server' in my.ini file
	//    must be the name of the mysql service.
	$myIniFile = $c_mysqlVersionDir.'/mysql'.$oneMysqlVersion.'/'.$mysqlConf['mysqlConfFile'];
	$myIniContents = file_get_contents($myIniFile);
	
	if(strpos($myIniContents, "[".$c_mysqlService."]") === false) {
		$myIniContents = preg_replace("/^\[wamp.*\]$/m", "[".$c_mysqlService."]", $myIniContents, 1, $count);
		if(!is_null($myIniContents) && $count == 1) {
			$fp = fopen($myIniFile,'w');
			fwrite($fp,$myIniContents);
			fclose($fp);
			$mysqlServer[$oneMysqlVersion] = 0;
		}
		else { //The MySQL server has not the same name as mysql service
			$mysqlServer[$oneMysqlVersion] = -1;
		}
	}
	else
		$mysqlServer[$oneMysqlVersion] = 0;
	unset($myIniContents);
	
	if ($oneMysqlVersion === $wampConf['mysqlVersion'] && $mysqlServer[$oneMysqlVersion] == 0)
  	$mysqlServer[$oneMysqlVersion] = 1;

	if ($mysqlServer[$oneMysqlVersion] == 1) {
    $myreplace .= 'Type: item; Caption: "'.$oneMysqlVersion.'"; Action: multi; Actions:switchMysql'.$oneMysqlVersion.'; Glyph: 13
';
	}
  elseif($mysqlServer[$oneMysqlVersion] == 0) {
    $myreplace .= 'Type: item; Caption: "'.$oneMysqlVersion.'"; Action: multi; Actions:switchMysql'.$oneMysqlVersion.'
';
  	$myreplacemenu .= '[switchMysql'.$oneMysqlVersion.']
Action: service; Service: '.$c_mysqlService.'; ServiceAction: stop; Flags: ignoreerrors waituntilterminated
Action: run; FileName: "'.$c_mysqlExe.'"; Parameters: "'.$c_mysqlServiceRemoveParams.'"; ShowCmd: hidden; Flags: ignoreerrors waituntilterminated
Action: closeservices;
Action: run; FileName: "'.$c_phpCli.'";Parameters: "switchMysqlVersion.php '.$oneMysqlVersion.'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated 
Action: run; FileName: "'.$c_mysqlVersionDir.'/mysql'.$oneMysqlVersion.'/'.$mysqlConf['mysqlExeDir'].'/'.$mysqlConf['mysqlExeFile'].'"; Parameters: "'.$mysqlConf['mysqlServiceInstallParams'].'"; ShowCmd: hidden; Flags: waituntilterminated
Action: run; FileName: "net"; Parameters: "start '.$c_mysqlService.'"; ShowCmd: hidden; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . refresh.php";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: resetservices; 
Action: readconfig;

';
	}
  elseif($mysqlServer[$oneMysqlVersion] == -1) {
    $myreplace .= 'Type: item; Caption: "'.$oneMysqlVersion.'"; Action: multi; Actions:switchMysql'.$oneMysqlVersion.'; Glyph: 19
';
  	$myreplacemenu .= '[switchMysql'.$oneMysqlVersion.']
Action: run; FileName: "'.$c_phpExe.'";Parameters: "msg.php 13 '.base64_encode($myIniFile).' '.base64_encode($c_mysqlService).'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
';
	}

}
/*$myreplace .= 'Type: separator;
Type: item; Caption: "Get more..."; Action: run; FileName: "'.$c_navigator.'"; Parameters: "http://www.wampserver.com/addons_mysql.php";
';
*/
$tpl = str_replace($myPattern,$myreplace.$myreplacemenu,$tpl);

//[modif oto] - Submenu Projects
if(strpos($tpl,";WAMPPROJECTSUBMENU") !== false && isset($wampConf['ProjectSubMenu']) && $wampConf['ProjectSubMenu'] == "on")
{
	//Add item for submenu
	$myPattern = ';WAMPPROJECTSUBMENU';
	$myreplace = $myPattern."
";
	$myreplacesubmenu = 'Type: submenu; Caption: "'.$w_projectsSubMenu.'"; Submenu: myProjectsMenu; Glyph: 3
';
	$tpl = str_replace($myPattern,$myreplace.$myreplacesubmenu,$tpl);
	
	//Add submenu
	$myPattern = ';WAMPMENULEFTEND';
	$myreplace = $myPattern."
";
	$myreplacesubmenu = '

[myProjectsMenu]
;WAMPPROJECTMENUSTART
;WAMPPROJECTMENUEND
	
';
	$tpl = str_replace($myPattern,$myreplace.$myreplacesubmenu,$tpl);

	//Construct submenu
	$myPattern = ';WAMPPROJECTMENUSTART';
	$myreplace = $myPattern."
";
	// Place projects into submenu Hosts
	// Folder to ignore in projects
	$projectsListIgnore = array ('.','..');
	// List projects
	$myDir = $wwwDir;
	if(substr($myDir,-1) != "/")
		$myDir .= "/";
	$handle=opendir($myDir);
	$projectContents = array();
	while (($file = readdir($handle))!==false) 
	{
		if (is_dir($myDir.$file) && !in_array($file,$projectsListIgnore)) 
			$projectContents[] = $file;
	}
	closedir($handle);
	$myreplacesubmenuProjects = '';
	if (count($projectContents) > 0)
	{
		for($i = 0 ; $i < count($projectContents) ; $i++)
		{ //[modif oto] Support de suppressLocalhost dans wampmanager.conf
			$myreplacesubmenuProjects .= 'Type: item; Caption: "'.$projectContents[$i].'"; Action: run; FileName: "'.$c_navigator.'"; Parameters: "'.($c_suppressLocalhost ? "http://" : 'http://localhost/').$projectContents[$i].'/"; Glyph: 5
';
		}
	}
	$tpl = str_replace($myPattern,$myreplace.$myreplacesubmenuProjects,$tpl);
}

//[modif oto] - Submenu Virtual Hosts
if(strpos($tpl,";WAMPVHOSTSUBMENU") !== false && isset($wampConf['VirtualHostSubMenu']) && $wampConf['VirtualHostSubMenu'] == "on")
{
	if(in_array("#Include conf/extra/httpd-vhosts.conf", $myhttpd) !== false)
		error_log ("conf/extra/httpd-vhosts.conf file is not included in conf/httpd.conf");
	else
	{
		$c_vhostConfFile = $c_apacheVersionDir.'/apache'.$wampConf['apacheVersion'].'/'.$wampConf['apacheConfDir'].'/extra/httpd-vhosts.conf';
		if(!file_exists($c_vhostConfFile))
			error_log ("file conf/extra/httpd-vhosts.conf does not exists");
		else
		{
			//Add item for submenu
			$myPattern = ';WAMPVHOSTSUBMENU';
			$myreplace = $myPattern."
";
			$myreplacesubmenu = 'Type: submenu; Caption: "'.$w_virtualHostsSubMenu.'"; Submenu: myVhostsMenu; Glyph: 3
';
			$tpl = str_replace($myPattern,$myreplace.$myreplacesubmenu,$tpl);
	
			//Add submenu
			$myPattern = ';WAMPMENULEFTEND';
			$myreplace = $myPattern."
";
			$myreplacesubmenu = '

[myVhostsMenu]
;WAMPVHOSTMENUSTART
;WAMPVHOSTMENUEND
	
';
			$tpl = str_replace($myPattern,$myreplace.$myreplacesubmenu,$tpl);

			//Construct submenu
			$myPattern = ';WAMPVHOSTMENUSTART';
			$myreplace = $myPattern."
";
			$myreplacesubmenuVhosts = '';
			// Place projects into submenu Hosts
			$myVhostsContents = file_get_contents($c_vhostConfFile);
			// Extract values of ServerName (without # at the beginning of the line)
			// Count number of <Directory that has to match the number of ServerName
			$nb_Server = preg_match_all("/^(?![\s\t]*#).*ServerName(.*)$/m", $myVhostsContents, $Server_matches);
			$nb_Directory = preg_match_all("/^(?![\s\t]*#).*<Directory.*$/m", $myVhostsContents, $Dir_matches);
			$nb_End_Directory = preg_match_all("~^(?![\s\t]*#).*</Directory.*$~m", $myVhostsContents, $end_Dir_matches);
						
			$server_name = array();
			if($nb_Server > 0)
			{
				foreach($Server_matches[1] as $value)
				{
					$value = trim($value);
					if(strpos($value,"dummy-host") === false) {
						//Validity of ServerName (Like domain name) - If not, red square at the left of VirtualHost
						//   /^[A-Za-z0-9]([-.](?![-.])|[A-Za-z0-9]){1,60}[A-Za-z0-9]$/
						if(preg_match('/^
							[A-Za-z0-9]			# letter or number at the beginning
							(								# characters neither at the beginning nor at the end
								[-.](?![-.])	#  a . or - not followed by . or -
										|					#   or
								[A-Za-z0-9]		#  a letter or a number
							){1,60}					# this, repeated from 1 to 60 times
							[A-Za-z0-9]			# letter ou number at the end
							$/x',$value) == 0) {
							$myreplacesubmenuVhosts .= 'Type: item; Caption: "'.$value.'"; Action: multi; Actions: server_'.$value.'; Glyph: 11
';    	
							$server_name[$value] = -1;
						}
						else {
							$myreplacesubmenuVhosts .= 'Type: item; Caption: "'.$value.'"; Action: run; FileName: "'.$c_navigator.'"; Parameters: "http://'.$value.'/"; Glyph: 5
';    	
							$server_name[$value] = 1;
						}
					}
				}
				if($nb_Server != $nb_Directory || $nb_End_Directory != $nb_Directory) {
					$value = "ServerName_Directory";
					$server_name[$value] = -2;
					$myreplacesubmenuVhosts .= 'Type: item; Caption: "'.$value.'"; Action: multi; Actions: server_'.$value.'; Glyph: 11
';    	
				}
				
				foreach($server_name as $name=>$value) {
					if($server_name[$name] == -1) {
    				$myreplacesubmenuVhosts .= '[server_'.$name.']
Action: run; FileName: "'.$c_phpExe.'";Parameters: "msg.php 9 '.base64_encode($name).'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
';
					}
					elseif($server_name[$name] == -2) {
						$message = "In the httpd-vhosts.conf file:\n\n\tThe number of\n\n\t\t<Directory ...>\n\t\t</Directory>\n\n\tis not equal to the number of\n\n\t\tServerName\n\nThey should be identical.";
    				$myreplacesubmenuVhosts .= '[server_'.$name.']
Action: run; FileName: "'.$c_phpExe.'";Parameters: "msg.php 11 '.base64_encode($message).'";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
';
					}
				}
			}
			$tpl = str_replace($myPattern,$myreplace.$myreplacesubmenuVhosts,$tpl);
		}
	}
}

//[modif oto] Right submenu Wampmanager settings
if(strpos($tpl,";WAMPSETTINGSSTART") !== false) {	
	// on recupere les valeurs dans wampConf
	foreach($wamp_Param as $value)
	{
	  if (isset($wampConf[$value]))
	  {
	    $wampConfParams[$value] = $value;
	    if ($wampConf[$value] == 'on')
	      $params_for_wampconf[$value] = '1';
	    elseif ($wampConf[$value] == 'off')
	      $params_for_wampconf[$value] = '0';
	    else
	      $params_for_wampconf[$value] = '-1';
	  }
	  else {//Param�tre n'existe pas dans wampserver.conf
	    $params_for_wampconf[$value] = -1;
	    $wampConfParams[$value] = $value;
	  }
	}
	
	$wampConfText = ";WAMPSETTINGSSTART
";
	foreach ($params_for_wampconf as $paramname=>$paramstatus)
	{
	  if ($params_for_wampconf[$paramname] == 1)
	    $wampConfText .= 'Type: item; Caption: "'.$w_settings[$paramname].'"; Glyph: 13; Action: multi; Actions: '.$wampConfParams[$paramname].'
';
	  elseif ($params_for_wampconf[$paramname] == 0)
	    $wampConfText .= 'Type: item; Caption: "'.$w_settings[$paramname].'"; Action: multi; Actions: '.$wampConfParams[$paramname].'
';
	  elseif ($params_for_wampconf[$paramname] == -1)
	    $wampConfText .= 'Type: item; Caption: "'.$w_settings[$paramname].'"; Action: multi; Actions: '.$wampConfParams[$paramname].' ;Glyph: 19; 
';}
	
	foreach ($params_for_wampconf as $paramname=>$paramstatus)
	{
	  if ($params_for_wampconf[$paramname] == 1)
	  	$wampConfText .= '['.$wampConfParams[$paramname].']
Action: service; Service: '.$c_apacheService.'; ServiceAction: stop; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "switchWampParam.php '.$wampConfParams[$paramname].' off";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . refresh.php";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "net"; Parameters: "start '.$c_apacheService.'"; ShowCmd: hidden; Flags: waituntilterminated
Action: resetservices
Action: readconfig;
';
	  elseif ($params_for_wampconf[$paramname] == 0) 
	  	$wampConfText .= '['.$wampConfParams[$paramname].']
Action: service; Service: '.$c_apacheService.'; ServiceAction: stop; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "switchWampParam.php '.$wampConfParams[$paramname].' on";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . refresh.php";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "net"; Parameters: "start '.$c_apacheService.'"; ShowCmd: hidden; Flags: waituntilterminated
Action: resetservices
Action: readconfig;
';
	  elseif ($params_for_wampconf[$paramname] == -1) 
	  	$wampConfText .= '['.$wampConfParams[$paramname].']
Action: service; Service: '.$c_apacheService.'; ServiceAction: stop; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "switchWampParam.php '.$wampConfParams[$paramname].' create";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "'.$c_phpCli.'";Parameters: "-c . refresh.php";WorkingDir: "'.$c_installDir.'/scripts"; Flags: waituntilterminated
Action: run; FileName: "net"; Parameters: "start '.$c_apacheService.'"; ShowCmd: hidden; Flags: waituntilterminated
Action: resetservices
Action: readconfig;
';
    
	}
	
	$tpl = str_replace(';WAMPSETTINGSSTART',$wampConfText,$tpl);
}

// ************************
//on enregistre le fichier wampmanager.ini

$fp = fopen($wampserverIniFile,'w');
fwrite($fp,$tpl);
fclose($fp);

//[modif oto] - Checking symbolic links from Apache/bin
//  on the dll files and phpForApache.ini in the active version of php 
linkPhpDllToApacheBin($wampConf['phpVersion']);

?>