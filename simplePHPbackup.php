<?php 
/*****
* Simple Backup-Script
* Parameters: see $params array. Input format= -paramname=paramvalue
* 
*/

// Default Params
$params=array();
// Source directories
$params['sourcedir']="/Users/thomaskahl/";
$params['source']="PhpstormProjects";

// Destination directory for the ZIP-Backup
$params['dest']="/Volumes/HDClone/Backup/Automatic";

// TMP-Directory for ZIP
$params['tmp']="/Users/thomaskahl/tmp";

// Password-protect ZIP-Backup
$params['pw']="backup12345";

// Split backup-zip
$params['split']="180m";

// Interval for incremental backups: d=daily, w=weekly, m=monthly, y= yearly - default: weekly
$params['interval']="w";

// Exclude files or dirs. To add to default set '+' at the beginning of param-value
$params['excl']="*.svn/* *.git/* *.idea/* ";

// Unused
$params['mask']="*";
$params['recursive']=true;

// Add FTP-Parameters to upload the file(s) after backup
$params['ftpserver']="";
$params['ftpuser']="";
$params['ftppw']="";
$params['ftppath']="/";

// leave empty or add parameter with empty string to prevent email
$params['mail']="t.kahl@vmx-pro.de";

// [MAC-ONLY] If the notifier Application is installed, use it to display a system notification
$params['notifier'] = file_exists('/Applications/Tools/terminal-notifier.app/Contents/MacOS/terminal-notifier');

// Parse the Parameters and replace the defaults with the commandline-params
foreach($argv as $arg) {
	if(substr($arg,0,1)=='-') {
		$arg=substr($arg, 1);
		$tmparg=explode("=", $arg);
		$params[$tmparg[0]]=(substr($tmparg[1],0,1)=='+') ? $params[$tmparg[0]].substr($tmparg[1], 1) : $tmparg[1];
	}
}

// Process the parameters that need some extras
// Temp-Dir for ZIP
$tmpdir="-b '".$params['tmp']."'";

// Password for ZIP-File (with option)
$pw= $params['pw'] ? '-P '.$params['pw'] : '';

// Split-Size (with Option)
$split=$params['split'] ? '-s '.$params['split'] : '';

// Excluded files and directories 
$excludes=' -x '.$params['excl'];

// Basedir for the backup (folders are created below this dir)
$newdir=$params['dest'];

// Get full path to backup-dir from parameters
$source=$params['sourcedir'].$params['source'];

// Set directory-date
// Incremental Backups per directory
$date=@date("Y-W"); 		//weekly as default
if($params['interval']=='m') {
	$date=@date("Y-m"); 		//monthly
} elseif ($params['interval']=='d') {
	$date=@date("Y-m-d"); 		//daily
} elseif ($params['interval']=='y') {
	$date=@date("Y"); 			//yearly
}

// ********* Build Paths ********* 

$project=strtolower($params['source']);
$projectarr=explode('/',$project);

$dir=end($projectarr);
$dir=strtolower(preg_replace('/[^a-zA-Z0-9_]/','.',$dir));

// Create Directories
if(count($projectarr)) {
	foreach($projectarr as $pd) {
		$newdir.='/'.strtolower(preg_replace('/[^a-zA-Z0-9_]/','.',$pd));
		if (!is_dir($newdir)) mkdir($newdir);
	}
}

// Create final backupdir for the date
$newdir = $newdir."/".$date."/";
if (!is_dir($newdir)) mkdir($newdir);

// Create Filename
// Timestamp for incremental backup filename
$filedate=@date("Y-m-d_H-i-s");

//Filename incremental Backup
$fileinc=$dir.'_'.$filedate.'.zip';

// Full path to incremental backup
$newinc=$newdir.$fileinc;

// Filename Full Backup
$file=$dir.'.zip';
$newfile=$newdir.$file;

// If the full Backup already exists
if (file_exists($newfile)) {
	// Create an incremental backup based on the full backup
	$arg = "-v -r $plit -X -li $tmpdir $pw '$newfile' '$source' -DF --out '$newinc' -q $excludes";
	$newzip=$newinc;
	// Find the name of the previous backup-file
	$lastzip=newestfiles($newdir);
	$lastzip=count($lastzip) ? $newdir.$lastzip[0] : '';
} else {
	// Or create the full backup
	$arg = "-u -v -r $split -X $tmpdir $pw -li '$newfile' '$source' -q $excludes";
	$newzip=$newfile;
	$lastzip='';
}

system("cd $source");
$zip_result=system("zip $arg",$retval);

$filelist=glob(str_replace('.zip', '', $newzip).'.*');

if($lastzip!='' && md5_file($lastzip)==md5_file($newzip)) {
	// Delete $newzip
	unlink($newzip);
	$msg='No files changed since last Backup. No file created…';
} else {
	$msg='New / changed files found. New backup file written: '.basename($newinc);
	// Upload to FTP
	if($params['ftpserver'] && $params['ftpuser'] && $params['ftppw'] && count($filelist)) $msg.= (saveToFtp($params['ftpserver'], $params['ftpuser'], $params['ftppw'], $params['ftppath'], $filelist)) ? 'FTP-Upload erfolgreich' : 'FTP-Upload fehlgeschlagen';
}

$output = "
####################################################################
#### ".$msg."
####################################################################
Project= $project
Dir= $dir
File= ".basename($newinc)."
Compare to= ".basename($lastzip)."
In Dir= $newdir
--------------------------------------------------------------------
Command= zip $arg
--------------------------------------------------------------------
";

foreach($filelist as $outfile) {
	$output.=str_replace($params['dest'], '...', $outfile)."\n";
}

// Don't show Password in mailbody
$output = str_replace($pw,'**pw**',$output);

if($params['mail']) {
	$Name = "Backup-User"; //senders name 
	$email = "tk@thomas-kahl.net"; //senders e-mail adress 
	$recipient = $params['mail']; //recipient 
	$mail_body = $output; //mail body 
	$subject = "Backup"; //subject 
	$header = "From: ". $Name . " <" . $email . ">\r\n"; //optional headerfields 
	mail($recipient, $subject, $mail_body, $header); //mail command :) 
}

echo $output;

// Using the terminal notifier from this source: https://github.com/alloy/terminal-notifier/downloads
if($params['notifier']) system("/Applications/Tools/terminal-notifier.app/Contents/MacOS/terminal-notifier -group Backup -message '$msg' -title 'Backup vollendet' -subtitle '$source'");



function compare($a,$b) {
  return $b[1] - $a[1];
}

function newestfiles($dir, $max=1) {
	$files=Array();
	$retval=array();
	$f=opendir($dir);

	while (($file=readdir($f))!==false) {
	  if(is_file("$dir/$file")) {
	    $files[]=Array($file,filemtime("$dir/$file"));
	  }
	}
	closedir($f);
	usort($files,"compare");
	$m=min($max,count($files));
	for ($i=0;$i<$m;$i++) {
	  $retval[]=$files[$i][0];
	}
	return $retval;
}

function saveToFtp($ftp_server,$ftp_user_name,$ftp_user_pass,$dest_path,$files) {
	// Verbindung aufbauen
	$conn_id = ftp_connect($ftp_server);

	// Login mit Benutzername und Passwort
	$login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);

	// Verbindung überprüfen
	if ((!$conn_id) || (!$login_result)) {
	    echo "FTP-Verbindung ist fehlgeschlagen!";
	    echo "Verbindungsaufbau zu $ftp_server mit Benutzername $ftp_user_name versucht.";
	    return false;
	} else {
	    echo "Verbunden zu $ftp_server mit Benutzername $ftp_user_name";
	}

	foreach($files as $file) {
		// Datei hochladen
		$destination_file=$dest_path.basename($file);
		$source_file=$file;
		$upload = ftp_put($conn_id, $destination_file, $source_file, FTP_BINARY);

		// Upload überprüfen
		if (!$upload) {
		    echo "FTP-Upload ist fehlgeschlagen!";
		} else {
		    echo "Datei $source_file auf Server $ftp_server als $destination_file hochgeladen";
		}
	}
	// Verbindung schließen
	ftp_close($conn_id);
}