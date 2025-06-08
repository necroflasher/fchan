<?PHP

function process_up()
{
	# text fields

	$subject = trim(userstr(@$_POST['subject']));
	$name    = trim(userstr(@$_POST['name']));
	$body    = trim(userstr(@$_POST['body']));

	$subject or $body or die('Error: Subject or comment required.');

	# file fields

	$fname_raw = userstr(@$_FILES["file"]["name"]);
	$tmpfile   = userstr(@$_FILES["file"]["tmp_name"]);
	$fsize     = @$_FILES["file"]["size"];
	$md5       = null;

	$fname_raw and $tmpfile   or die('Error: No file.');
	$fsize                    or die('Error: Empty file.');
	!$_FILES["file"]["error"] or die('Error: Upload failed.');

	# file derived

	$fname = pathinfo($fname_raw, PATHINFO_FILENAME);
	$fext  = '.'.pathinfo($fname_raw, PATHINFO_EXTENSION);

	$err = userfilename($fname);
	$err and die("Error: Bad filename. ($err)");

	@EXTS[$fext] or die('Error: Unsupported filetype.');

	# final stuff

	$md5 = md5_file($_FILES["file"]["tmp_name"], true);

	$body = htmlspecialchars($body);
	$body = preg_replace('/\n/', '<BR>', $body);

	$fpurge_dat = null;
	$err = db_up([
		'subject' => htmlspecialchars($subject),
		'name'    => htmlspecialchars($name),
		'body'    => $body,
		'md5'     => $md5,
		'fname'   => $fname,
		'fext'    => $fext,
		'fsize'   => $fsize,
	], $fpurge_dat);

	$err and die("Error: $err");

	move_uploaded_file($tmpfile, FILES_DIR.'/'.$fname.$fext);

	foreach ($fpurge_dat as $dat)
		unlink(FILES_DIR.'/'.$dat['fname'].$dat['fext']);

	echo 'Upload complete. <A href="',FRONT_PUBLIC,'">Return</A>';
}

function process_re()
{
	$nam = trim(strval(@$_POST['name']));
	$com = trim(strval(@$_POST['body']));
	$tno = userint(@$_POST['no']);

	$com or die('Error: No text entered.');

	$nam = htmlspecialchars($nam);
	$com = htmlspecialchars($com);
	$com = preg_replace('/\n/', '<BR>', $com);

	$err = db_re([
		'tno'  => $tno,
		'name' => $nam,
		'body' => $com,
	]);

	$err and die("Error: $err");

	echo 'Post created. <A href="',FRONT_PUBLIC,'?v=thread&no=',$tno,'">Return</A>';
}

function process_del()
{
	$tno = userint(@$_POST['tno']);
	$cno = userint(@$_POST['cno']);

	$dat = null;
	$err = db_del($tno, $cno, $dat);
	$err and die("Error: $err");

	if ($dat['fname'])
		unlink(FILES_DIR.'/'.$dat['fname'].$dat['fext']);

	echo 'Post deleted. <A href="',FRONT_PUBLIC,'?v=thread&no=',$tno,'">Return</A>';
}
