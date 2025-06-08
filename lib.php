<?PHP

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
		return number_format($bytes/1024, 2).' KB';
	else
		return number_format($bytes/(1024*1024), 2).' MB';
}
