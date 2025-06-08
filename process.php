<?PHP

function process_up()
{
	# text fields

	$subject = trim(userstr(@$_POST['subject']));
	$name    = trim(userstr(@$_POST['name']));
	$body    = trim(userstr(@$_POST['body']));
	$pass    = userstr(@$_POST['pass']);

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

	$tno = null;
	$fpurge_dat = null;
	$err = db_up([
		'subject' => $subject,
		'name'    => $name,
		'body'    => $body,
		'md5'     => $md5,
		'fname'   => $fname,
		'fext'    => $fext,
		'fsize'   => $fsize,
		'pass'    => $pass,
	], $tno, $fpurge_dat);

	$err and die("Error: $err");

	move_uploaded_file($tmpfile, FILES_DIR.'/'.$fname.$fext);

	foreach ($fpurge_dat as $dat)
		unlink(FILES_DIR.'/'.$dat['fname'].$dat['fext']);

	echo 'Upload complete. <A href="',FRONT_PUBLIC,'?v=thread&no=',$tno,'">View</A>';
}

function process_re()
{
	$name = trim(userstr(@$_POST['name']));
	$body = trim(userstr(@$_POST['body']));
	$pass = userstr(@$_POST['pass']);
	$tno  = userint(@$_POST['no']);

	$body or die('Error: No text entered.');

	$body = htmlspecialchars($body);
	$body = preg_replace('/\n/', '<BR>', $body);

	$cno = null;
	$err = db_re([
		'tno'  => $tno,
		'name' => $name,
		'body' => $body,
		'pass' => $pass,
	], $cno);
	$err and die("Error: $err");

	echo 'Post created. <A href="',FRONT_PUBLIC,'?v=thread&no=',$tno,'#c',$cno,'">View</A>';
}

function process_del()
{
	$tno  = userint(@$_POST['tno']);
	$cno  = userint(@$_POST['cno']);
	$pass = userstr(@$_POST['pass']);

	$dat = null;
	$err = db_del($tno, $cno, $pass, $dat);
	$err and die("Error: $err");

	if ($dat['fname'])
		unlink(FILES_DIR.'/'.$dat['fname'].$dat['fext']);

	echo 'Post deleted. <A href="',FRONT_PUBLIC,'?v=thread&no=',$tno,'#c',$cno,'">Return</A>';
}
