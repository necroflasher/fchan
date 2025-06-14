<?PHP

function render_front()
{
	$pass = userstr(@$_COOKIE['pass']);

	if (!$pass)
		$pass = genpass();

	html_start(200, 'fchan');

	echo '<STYLE type="text/css"><!--';
	echo '.wrap { word-wrap: break-word; overflow-wrap: anywhere; }';
	echo '--></STYLE>';

	echo '<FORM action="',FRONT_PUBLIC,'" method="POST" enctype="multipart/form-data">';
	echo '<INPUT type="hidden" name="p" value="up">';
	echo '<TABLE border>';
	echo '<TR>';
	echo   '<TH><LABEL for="subject">Subject</LABEL>';
	echo   '<TD><INPUT dir="auto" type="text" name="subject" id="subject">';
	echo '<TR>';
	echo   '<TH><LABEL for="name">Name</LABEL>';
	echo   '<TD><INPUT dir="auto" type="text" name="name" id="name">';
	echo '<TR>';
	echo   '<TH><LABEL for="body">Comment</LABEL>';
	echo   '<TD><TEXTAREA dir="auto" name="body" id="body" rows=4 cols=40></TEXTAREA>';
	echo '<TR>';
	echo   '<TH><LABEL for="file">File</LABEL>';
	echo   '<TD><INPUT type="file" name="file" id="file">';
	echo '<TR>';
	echo   '<TH><LABEL for="tag">Tag</LABEL>';
	echo   '<TD>';
	echo   '<SELECT name="tag" id="tag">';
	foreach (LONGTAGS as $i => $t)
		echo '<OPTION value="',$i,'">',$i?$t:'Choose one:','</OPTION>';
	echo   '</SELECT>';
	echo '<TR>';
	echo   '<TH><LABEL for="pass">Password</LABEL>';
	echo   '<TD><INPUT dir="auto" type="text" name="pass" id="pass" value="',htmlspecialchars($pass),'">';
	echo '<TR>';
	echo   '<TH>';
	echo   '<TD><INPUT type="submit">';
	echo '</TABLE>';
	echo '</FORM>';
	echo '<HR>';

	echo '<TABLE border>';
	echo '<TR>';
	echo '<TH>No.';
	echo '<TH>Name';
	echo '<TH>File';
	echo '<TH>Tag';
	echo '<TH>Subject';
	echo '<TH>Size';
	echo '<TH>Date';
	echo '<TH>Replies';
	echo '<TH>';
	foreach (db_get_front() as $t)
	{
		$nametitle = '';
		if (!$t['name'])
			$nametext = 'Anonymous';
		else if (mb_strlen($t['name']) <= 20)
			$nametext = htmlspecialchars($t['name']);
		else
		{
			$nametext = htmlspecialchars(mb_substr($t['name'], 0, 20)).'(...)';
			$nametitle = ' title="'.htmlspecialchars($t['name']).'"';
		}

		$subject = $t['subject'];
		if (!$subject)
		{
			$subject = preg_replace('/(<BR>|\n)(.|\n)*$/', '', $t['body']);
			$subject = preg_replace('/<[^>]*>/', '', $subject);
			$subject = html_entity_decode($subject);
		}

		$subjtitle = '';
		if (mb_strlen($subject) <= 20)
			$subjtext = htmlspecialchars($subject);
		else
		{
			$subjtext = htmlspecialchars(mb_substr($subject, 0, 20)).'(...)';
			$subjtitle = ' title="'.htmlspecialchars($subject).'"';
		}

		$ftitle = '';
		if (mb_strlen($t['fname']) <= 20)
			$ftext = '<SPAN dir="auto" lang>'.htmlspecialchars($t['fname']).'</SPAN>'.$t['fext'];
		else
		{
			$ftext = '<SPAN dir="auto" lang>'.htmlspecialchars(mb_substr($t['fname'], 0, 20)).'(...)'.'</SPAN>'.$t['fext'];
			$ftitle = ' title="'.htmlspecialchars($t['fname']).'"';
		}

		echo '<TR>';
		echo '<TD align="right">';
		echo   $t['tno'];
		echo '<TD dir="auto" lang class="wrap">';
		echo   '<B',$nametitle,'>';
		echo   $nametext;
		echo   '</B>';
		echo '<TD dir="auto" lang><A';
		echo   ' href="',FILES_DIR_PUBLIC,'/',htmlspecialchars($t['fname']),$t['fext'],'"';
		echo   ' data-md5="',bin2hex($t['md5']),'"';
		echo   ' data-size="',$t['fsize'],'"';
		echo   $ftitle;
		echo   '>',$ftext,'</A>';
		echo '<TD>';
		echo   '[',SHORTTAGS[$t['ftag']?$t['ftag']:0],']';
		echo '<TD dir="auto" lang class="wrap">';
		echo   '<B',$subjtitle,'>';
		echo   $subjtext;
		echo   '</B>';
		echo '<TD>';
		echo   '<SPAN title="',$t['fsize'],' bytes">';
		echo   format_fs($t['fsize']);
		echo   '</SPAN>';
		echo '<TD>';
		echo   date('Y-m-d', floor($t['tcreated']));
		echo '<TD align="right">';
		echo   $t['coms'];
		echo '<TD>';
		echo   '<A href="?v=thread&no=',$t['tno'],'">Reply</A>';
		if ($t['numnewer'] >= 30-5)
			echo '<SPAN title="This thread is old and will be closed soon.">*</SPAN>';
	}
	echo '</TABLE>';

	html_end();
}

function render_thread()
{
	$tno  = userint(@$_GET['no']);
	$pass = userstr(@$_COOKIE['pass']);

	if (!$pass)
		$pass = genpass();

	$dat = db_get_thread($tno);

	$dat or html_die(404, 'Error: Thread not found.');

	$title = $dat[0]['subject'];
	if (!$title)
	{
		$title = $dat[0]['body'];
		$title = preg_replace('/<BR>.*/', '', $title);
		$title = preg_replace('/<[^>]*>/', '', $title);
		$title = htmlspecialchars_decode($title);
	}
	if ($title)
		$title = mb_substr($title, 0, 80);
	else
		$title = 'No. '.$tno;

	html_start(200, $title.' @ fchan');

	echo '<STYLE type="text/css"><!--';
	echo '.wrap { word-wrap: break-word; overflow-wrap: anywhere; }';
	echo '--></STYLE>';

	echo '<FONT size="+1">';
	echo '<B dir="auto" lang class="wrap">';
	echo $dat[0]['subject']?htmlspecialchars($dat[0]['subject']):'No subject';
	echo '</B>';
	echo '</FONT>';
	echo '<HR>';

	$delpost = [
		'name' => 'Anonymous',
		'body' => '<I>Post deleted.</I>',
		'md5'  => null,
	];
	foreach ($dat as $t)
	{
		$cno = $t['cno'];

		if ($t['tdeleted'])
		{
			$delpost['tcreated'] = $t['tcreated'];
			$t = $delpost;
		}

		echo $cno;
		echo ': ';
		echo '<B dir="auto" lang class="wrap" id="c',$cno,'">';
		echo $t['name']?htmlspecialchars($t['name']):'Anonymous';
		echo '</B>';
		echo ' ';
		echo date('Y-m-d(D) H:i:s', floor($t['tcreated']));
		echo '.';
		echo substr(sprintf('%.3f', fmod($t['tcreated'], 1.0)), 2);

		echo ' [';
		echo '<A href="?v=options&no=',$tno,'&com=',$cno,'">';
		echo 'Options';
		echo '</A>';
		echo ']';

		echo '<BLOCKQUOTE dir="auto" lang class="wrap">';
		if ($t['body'])
			echo $t['body'];
		if ($t['md5'])
		{
			if ($t['body'])
				echo '<BR><BR>';
			echo '<A';
			echo ' href="',FILES_DIR_PUBLIC,'/',htmlspecialchars($t['fname']),$t['fext'],'"';
			echo ' data-md5="',bin2hex($t['md5']),'"';
			echo ' data-size="',$t['fsize'],'"';
			echo '>';
			echo '<SPAN dir="auto" lang>',htmlspecialchars($t['fname']),'</SPAN>',$t['fext'];
			echo '</A>';
			echo ' (';
			echo format_fs($t['fsize']);
			echo ', ';
			echo LONGTAGS[$t['ftag']];
			echo ')';
			
		}
		echo '</BLOCKQUOTE>';
		echo '<HR>';
	}

	# checks: db.php db_re()
	$formdisable = '';
	if ($dat[0]['tdeleted'] ||
	    $dat[0]['tfpurged'] ||
	    count($dat) >= 1000)
	{
		$formdisable = ' disabled="disabled"';
		echo '<P>&#x25a0; This thread is closed for new comments.';
	}
	else if ($dat[0]['numnewer'] >= 30-5)
	{
		echo '<P>&#x25a1; This thread is old and will be closed soon.';
	}

	echo '<FORM action="',FRONT_PUBLIC,'" method="POST">';
	echo '<INPUT type="hidden" name="p" value="re">';
	echo '<INPUT type="hidden" name="no" value="',$tno,'">';
	echo '<TABLE border>';
	echo '<TR>';
	echo   '<TH><LABEL for="name">Name</LABEL>';
	echo   '<TD><INPUT dir="auto" type="text" name="name" id="name"',$formdisable,'>';
	echo '<TR>';
	echo   '<TH><LABEL for="body">Comment</LABEL>';
	echo   '<TD><TEXTAREA dir="auto" name="body" id="body" rows=4 cols=40',$formdisable,'></TEXTAREA>';
	echo '<TR>';
	echo   '<TH><LABEL for="pass">Password</LABEL>';
	echo   '<TD><INPUT dir="auto" type="text" name="pass" id="pass" value="',htmlspecialchars($pass),'"',$formdisable,'>';
	echo '<TR>';
	echo   '<TH>';
	echo   '<TD><INPUT type="submit"',$formdisable,'>';
	echo '</TABLE>';
	echo '</FORM>';

	html_end();
}

function render_options()
{
	$tno  = userint(@$_GET['no']);
	$cno  = userint(@$_GET['com']);
	$pass = userstr(@$_COOKIE['pass']);

	$t = db_get_comment($tno, $cno);

	$t or html_die(404, 'Error: Post not found.');

	if ($t['md5']) $t['md5'] = bin2hex($t['md5']);
	foreach (['tcreated', 'tdeleted', 'tfpurged'] as $k)
	{
		if (!$t[$k])
			continue;
		$t[$k] = strval($t[$k]).
		    ' <'.date('Y-m-d H:i:s', floor($t[$k])).'>';
	}

	html_start(200, 'Post details @ fchan');

	echo '<STYLE type="text/css"><!--';
	echo '.wrap { word-wrap: break-word; overflow-wrap: anywhere; }';
	echo '--></STYLE>';

	$ks = 'tno.cno.tcreated.tdeleted.tfpurged.subject.name.body.fname.fext.ftag.fsize.md5';
	if (isadmin())
		$ks .= ".ip*";
	echo '<TABLE border>';
	foreach (explode('.', $ks) as $k)
	{
		$v = $t[str_replace('*', '', $k)];
		if (!$v)
			continue;
		echo '<TR>';
		echo '<TH>',$k;
		echo '<TD dir="auto" lang class="wrap">',htmlspecialchars($v);
	}
	echo '</TABLE>';

	echo '<P>';

	$deldisabled = '';
	if ($t['tdeleted'])
		$deldisabled = ' disabled="disabled"';

	echo '<FORM action="',FRONT_PUBLIC,'" method="POST">';
	echo '<INPUT type="hidden" name="p" value="del">';
	echo '<INPUT type="hidden" name="tno" value="',$tno,'">';
	echo '<INPUT type="hidden" name="cno" value="',$cno,'">';
	echo '<FIELDSET',$deldisabled,'>';
	echo '<LEGEND>Delete post</LEGEND>';
	echo '<TABLE>';
	echo '<TR>';
	echo '<TD><LABEL for="pass">Password:</LABEL>';
	echo '<TD><INPUT dir="auto" type="text" name="pass" id="pass" value="',htmlspecialchars($pass),'">';
	echo '<TR>';
	echo '<TD colspan=2>';
	echo '<INPUT type="submit">';
	echo '</TABLE>';
	echo '</FIELDSET>';
	echo '</FORM>';

	html_end();
}
