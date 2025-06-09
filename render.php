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

		$subjtitle = '';
		if (mb_strlen($t['subject']) <= 20)
			$subjtext = htmlspecialchars($t['subject']);
		else
		{
			$subjtext = htmlspecialchars(mb_substr($t['subject'], 0, 20)).'(...)';
			$subjtitle = ' title="'.htmlspecialchars($t['subject']).'"';
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
		echo '<TD align="right">';
		echo   $t['coms'];
		echo '<TD>';
		echo   '<A href="?v=thread&no=',$t['tno'],'">Reply</A>';
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

	html_start(200, 'No. ',$tno,' @ fchan');

	echo '<STYLE type="text/css"><!--';
	echo '.wrap { word-wrap: break-word; overflow-wrap: anywhere; }';
	echo '--></STYLE>';

	echo '<FONT size="+1">';
	echo '<B dir="auto" lang class="wrap">';
	echo $dat[0]['subject']?htmlspecialchars($dat[0]['subject']):'No subject';
	echo '</B>';
	echo ' (',$tno,')';
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

		if ($t['deleted'])
			$t = $delpost;

		echo $cno;
		echo ': ';
		echo '<B dir="auto" lang class="wrap" id="c',$cno,'">';
		echo $t['name']?htmlspecialchars($t['name']):'Anonymous';
		echo '</B>';

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
			
		}
		echo '</BLOCKQUOTE>';
		echo '<HR>';
	}

	# checks: db.php db_re()
	$formdisable = '';
	if ($dat[0]['deleted'] ||
	    $dat[0]['fpurged'] ||
	    count($dat) >= 1000)
	{
		$formdisable = ' disabled="disabled"';
		echo '<P>&#x25a0; This thread is closed for new comments.';
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
	$t['time'] = date('Y-m-d H:i:s', $t['time']);

	html_start(200, 'Post details @ fchan');

	echo '<STYLE type="text/css"><!--';
	echo '.wrap { word-wrap: break-word; overflow-wrap: anywhere; }';
	echo '--></STYLE>';

	$ks = 'tno.cno.time.subject.name.body.fname.fext.ftag.fsize.md5.deleted.fpurged';
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
	if ($t['deleted'])
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
