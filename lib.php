<?PHP

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
