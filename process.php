<?PHP

function process_up()
{
	# text fields

	$subject = trim(userstr(@$_POST['subject']));
	$name    = trim(userstr(@$_POST['name']));
	$body    = trim(userstr(@$_POST['body']));
	$pass    = userstr(@$_POST['pass']);
	setcookie('pass', $pass);

	$subject or $body or html_die(400, 'Error: Subject or comment required.');

	# file fields

	$fname_raw = userstr(@$_FILES["file"]["name"]);
	$tmpfile   = userstr(@$_FILES["file"]["tmp_name"]);
	$fsize     = @$_FILES["file"]["size"];
	$md5       = null;

	$fname_raw and $tmpfile   or html_die(400, 'Error: No file.');
	$fsize                    or html_die(400, 'Error: Empty file.');
	!$_FILES["file"]["error"] or html_die(400, 'Error: Upload failed.');

	# file derived

	$fname = pathinfo($fname_raw, PATHINFO_FILENAME);
	$fext  = '.'.pathinfo($fname_raw, PATHINFO_EXTENSION);

	$err = userfilename($fname);
	$err and html_die(400, "Error: Bad filename. ($err)");

	@EXTS[$fext] or html_die(400, 'Error: Unsupported filetype.');

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

	if ($err === 'File exists.')
		html_die(400, 'Error: File exists. <A href="',FRONT_PUBLIC,'?v=thread&no=',$tno,'">View</A>');

	$err and html_die(400, "Error: $err");

	move_uploaded_file($tmpfile, FILES_DIR.'/'.$fname.$fext);

	foreach ($fpurge_dat as $dat)
		unlink(FILES_DIR.'/'.$dat['fname'].$dat['fext']);

	html_die(200, 'Upload complete. <A href="',FRONT_PUBLIC,'?v=thread&no=',$tno,'">View</A>');
}

function process_re()
{
	$name = trim(userstr(@$_POST['name']));
	$body = trim(userstr(@$_POST['body']));
	$pass = userstr(@$_POST['pass']);
	$tno  = userint(@$_POST['no']);
	setcookie('pass', $pass);

	$body or html_die(400, 'Error: No text entered.');

	$body = htmlspecialchars($body);
	$body = preg_replace('/\n/', '<BR>', $body);

	$cno = null;
	$err = db_re([
		'tno'  => $tno,
		'name' => $name,
		'body' => $body,
		'pass' => $pass,
	], $cno);
	$err and html_die(400, "Error: $err");

	html_die(200, 'Post created. <A href="',FRONT_PUBLIC,'?v=thread&no=',$tno,'&_=',time(),'#c',$cno,'">View</A>');
}

function process_del()
{
	$tno  = userint(@$_POST['tno']);
	$cno  = userint(@$_POST['cno']);
	$pass = userstr(@$_POST['pass']);
	setcookie('pass', $pass);

	$dat = null;
	$err = db_del($tno, $cno, $pass, $dat);
	$err and html_die(400, "Error: $err");

	if ($dat['fname'])
		unlink(FILES_DIR.'/'.$dat['fname'].$dat['fext']);

	html_die(200, 'Post deleted. <A href="',FRONT_PUBLIC,'?v=thread&no=',$tno,'&_=',time(),'#c',$cno,'">Return</A>');
}
