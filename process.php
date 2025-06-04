<?PHP

function process_up()
{
	array_key_exists('file', $_FILES) or die('Error: No file.');
	!$_FILES["file"]["error"]         or die('Error: Upload failed.');

	$sub = trim($_POST['subject']);
	$nam = trim($_POST['name']);
	$com = trim($_POST['body']);

	$sub || $com || die('Error: Subject or comment required.');

	$md5   = md5_file($_FILES["file"]["tmp_name"], true);
	$fname = pathinfo($_FILES["file"]["name"], PATHINFO_FILENAME);
	$fext  = '.'.pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION);
	$fsize = $_FILES["file"]["size"];

	$destfile = FILES_DIR.'/'.bin2hex($md5).$fext;

	//~ if (file_exists($destfile))
	if (!db_can_post_file($md5))
	{
		unlink($_FILES["file"]["tmp_name"]);
		die('Error: File exists.');
	}

	$com = htmlspecialchars($com);
	$com = preg_replace('/\n/', '<BR>', $com);

	$res = db_submit_file([
		'sub'   => htmlspecialchars($sub),
		'nam'   => htmlspecialchars($nam),
		'com'   => $com,
		'md5'   => $md5,
		'fname' => htmlspecialchars($fname),
		'fext'  => htmlspecialchars($fext),
		'fsize' => $fsize,
	]);

	if (!$res)
	{
		unlink($_FILES["file"]["tmp_name"]);
		die();
	}

	move_uploaded_file(
		$_FILES["file"]["tmp_name"],
		$destfile);

	echo 'Upload complete. <A href="',FRONT_PUBLIC,'">Return</A>';
}

function process_re()
{
	$nam = trim(strval(@$_POST['name']));
	$com = trim(strval(@$_POST['body']));
	$tno = $_POST['no'];

	$com or die('Error: No text entered.');

	$nam = htmlspecialchars($nam);
	$com = htmlspecialchars($com);
	$com = preg_replace('/\n/', '<BR>', $com);

	$ok = db_submit_comment([
		'tno'  => $tno,
		'name' => $nam,
		'body' => $com,
	]);

	$ok or die('Error: Failed to insert post.');

	echo 'Post created. <A href="',FRONT_PUBLIC,'?v=thread&no=',$tno,'">Return</A>';
}
