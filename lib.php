<?PHP

function html_start($status, ...$title)
{
	http_response_code($status);
	echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN"';
	echo ' "http://www.w3.org/TR/REC-html40/loose.dtd">';
	echo '<HTML lang="en">';
	echo '<HEAD>';
	echo   '<META http-equiv="Content-Type" content="text/html; charset=UTF-8">';
	echo   '<TITLE>';
	foreach ($title as $h) echo htmlspecialchars($h);
	echo   '</TITLE>';
	echo   '<META name="color-scheme" content="light dark">';
	echo   '<META name="viewport" content="width=device-width">';
	echo '</HEAD>';
	echo '<BODY>';
	echo '<B><I><A href="',FRONT_PUBLIC,'">fchan</A></I></B>';
	echo '<HR>';
}

function html_end()
{
	echo '<HR>';
	echo '</BODY>';
	echo '</HTML>';
}

function html_die($status, ...$html)
{
	html_start($status, ($status === 200) ? 'fchan' : 'error @ fchan');
	foreach ($html as $h) echo $h;
	html_end();
	die();
}

function userstr($s)
{
	if (!is_string($s))
		return '';

	return $s;

}

function userfilename($s)
{
	if (!is_string($s))
		return 'type error';

	if ($s === '' || strlen($s) > 127)
		return 'must be 1-127 bytes long';

	if (!mb_check_encoding($s, 'UTF-8'))
		return 'invalid utf-8';

	if ($s[0] === '.')
		return 'must not begin with a dot';

	if (preg_match('/[\0-\x1f\x7f]/', $s))
		return 'contains non-printable ascii';

	return '';
}

function userint($s)
{
	$u = 0;
	$n = -1;
	$rv = sscanf($s, '%u%n', $u, $n);
	if ($rv === 2 && $n === strlen($s) && strval($u) === $s)
		return $u;
	else
		return 0;
}

function format_fs($bytes)
{
	if ($bytes < 1024)
		return $bytes.' B';
	else if ($bytes < 1024*1024)
		return number_format($bytes/1024, 0).'&nbsp;KB';
	else
		return number_format($bytes/(1024*1024), 1).'&nbsp;MB';
}
