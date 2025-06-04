<?PHP

function format_fs($bytes)
{
	if ($bytes < 1024)
		return $bytes.' B';
	else if ($bytes < 1024*1024)
		return number_format($bytes/1024, 2).' KB';
	else
		return number_format($bytes/(1024*1024), 2).' MB';
}
